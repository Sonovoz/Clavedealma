<?php

use App\Filament\Resources\ArtistNotices\Pages\CreateArtistNotice;
use App\Filament\Resources\ArtistNotices\Pages\ListArtistNotices;
use App\Filament\Resources\ArtistNotices\Pages\ViewArtistNotice;
use App\Modules\Identity\Contracts\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Stores\Contracts\NoticeStatus;
use App\Modules\Stores\Infrastructure\Models\Artist;
use App\Modules\Stores\Infrastructure\Models\ArtistNotice;
use App\Modules\Stores\Infrastructure\Models\Store;
use App\Platform\Artists\Mail\ArtistNoticeMail;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Mail::fake();
    Storage::fake('local');
    Filament::setCurrentPanel('admin');

    $this->artistUser = User::factory()->withRole(Role::Artist)->create(['email' => 'luna@example.com']);
    $this->artist = Artist::factory()->create(['display_name' => 'Luna Bruma', 'user_id' => $this->artistUser->id]);
    Store::factory()->create(['artist_id' => $this->artist->id]);
    $this->admin = User::factory()->withRole(Role::Superadmin)->create(['email' => 'equipo@example.com']);
});

it('el artista envía un aviso con fecha y fotos, y llega a los administradores', function () {
    $this->actingAs($this->artistUser);

    Livewire::test(CreateArtistNotice::class)
        ->fillForm([
            'title' => 'Firmo camisetas en El Corte Inglés el día 12',
            'message' => 'Necesito 40 camisetas La esencia en M y L.',
            'event_date' => '2026-11-12',
            'attachments' => [UploadedFile::fake()->image('cartel.jpg')],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $notice = ArtistNotice::query()->sole();
    expect($notice->artist_id)->toBe($this->artist->id)
        ->and($notice->status)->toBe(NoticeStatus::New)
        ->and($notice->event_date?->format('Y-m-d'))->toBe('2026-11-12')
        ->and($notice->attachments)->toHaveCount(1);

    Mail::assertSent(ArtistNoticeMail::class, fn (ArtistNoticeMail $m): bool => $m->forTeam && $m->hasTo('equipo@example.com'));
    expect($this->admin->notifications()->count())->toBe(1);

    // El archivo es privado: lo abre el artista, no otro artista.
    $this->get(route('artist-notices.file', [$notice, 0]))->assertOk();
    $other = User::factory()->withRole(Role::Artist)->create();
    Artist::factory()->create(['user_id' => $other->id]);
    $this->actingAs($other)->get(route('artist-notices.file', [$notice, 0]))->assertForbidden();
});

it('cada artista solo ve sus avisos', function () {
    $mine = ArtistNotice::query()->create(['artist_id' => $this->artist->id, 'title' => 'El mío', 'message' => 'x']);
    $theirs = ArtistNotice::query()->create(['artist_id' => Artist::factory()->create()->id, 'title' => 'El de otro', 'message' => 'y']);

    $this->actingAs($this->artistUser);
    Livewire::test(ListArtistNotices::class)->assertCanSeeTableRecords([$mine])->assertCanNotSeeTableRecords([$theirs]);
    $this->get('/admin/avisos/'.$theirs->id)->assertNotFound();
});

it('el equipo lo marca «Hecho» con una respuesta y el artista recibe un email', function () {
    $notice = ArtistNotice::query()->create(['artist_id' => $this->artist->id, 'title' => 'Nueva camiseta con logo de temporada', 'message' => 'Te paso el logo.']);

    $this->actingAs($this->admin);
    $this->get('/admin/avisos')->assertOk()->assertSee('Avisos de artistas')->assertSee('Luna Bruma');
    Livewire::test(ListArtistNotices::class)->assertActionHidden('create');
    $this->get('/admin/avisos/nuevo')->assertForbidden();
    Livewire::test(ViewArtistNotice::class, ['record' => $notice->getRouteKey()])
        ->callAction('done', ['reply' => 'Publicada el lunes en Artist Voice.'])
        ->assertHasNoActionErrors();

    $notice->refresh();
    expect($notice->status)->toBe(NoticeStatus::Done)
        ->and($notice->reply)->toBe('Publicada el lunes en Artist Voice.')
        ->and($notice->handled_by)->toBe($this->admin->id);
    Mail::assertSent(ArtistNoticeMail::class, fn (ArtistNoticeMail $m): bool => ! $m->forTeam && $m->hasTo('luna@example.com'));

    // El artista ve la respuesta.
    $this->actingAs($this->artistUser)->get('/admin/avisos/'.$notice->id)->assertOk()->assertSee('Publicada el lunes en Artist Voice.');
});

it('el resto del equipo no ve los avisos y el artista no puede atenderlos', function () {
    $notice = ArtistNotice::query()->create(['artist_id' => $this->artist->id, 'title' => 'Aviso', 'message' => 'x']);

    $this->actingAs(User::factory()->withRole(Role::CatalogManager)->create())->get('/admin/avisos')->assertForbidden();

    $this->actingAs($this->artistUser);
    Livewire::test(ViewArtistNotice::class, ['record' => $notice->getRouteKey()])
        ->assertActionHidden('done')->assertActionHidden('inProgress');
});
