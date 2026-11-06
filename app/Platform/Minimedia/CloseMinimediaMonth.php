<?php

namespace App\Platform\Minimedia;

use App\Platform\Pdf\PdfRenderer;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Cierre mensual de Minimedia (ADR 0029): guarda las cifras del mes (una sola vez) y envía
 * el resumen a Minimedia, con el PDF y el CSV adjuntos. Lo lanza cada día la tarea
 * programada `minimedia:cierre`: el día 1 cierra el mes anterior, y si el email no salió,
 * lo reintenta al día siguiente.
 */
class CloseMinimediaMonth
{
    public function __construct(private readonly PdfRenderer $pdf) {}

    /** Cierra el mes (si ya estaba cerrado, devuelve el cierre que había: no se recalcula). */
    public function close(string $month): MinimediaStatement
    {
        $existing = MinimediaStatement::query()->where('month', $month)->first();
        if ($existing !== null) {
            return $existing;
        }

        $r = MinimediaCommission::compute($month);

        return MinimediaStatement::query()->create([
            'month' => $month, 'rate_bps' => $r['rate_bps'], 'orders' => $r['orders'],
            'sales_base_cents' => $r['sales_base'], 'refunds_base_cents' => $r['refunds_base'],
            'net_base_cents' => $r['net_base'], 'commission_cents' => $r['commission'], 'stores' => $r['stores'],
        ]);
    }

    /** El último mes terminado: el anterior al de hoy (hora de Madrid). */
    public static function previousMonth(?CarbonImmutable $now = null): string
    {
        return MinimediaCommission::monthKey(($now ?? CarbonImmutable::now())->timezone(MinimediaCommission::TZ)->startOfMonth()->subMonthNoOverflow());
    }

    public function send(MinimediaStatement $statement, ?string $to = null): bool
    {
        $to ??= MinimediaCommission::email();

        try {
            Mail::to($to)->send(new MinimediaStatementMail($statement, $this->pdf($statement), self::csv($statement)));
            $statement->update(['sent_to' => $to, 'sent_at' => now(), 'send_error' => null]);

            return true;
        } catch (Throwable $e) {
            report($e);
            $statement->update(['send_error' => Str::limit($e->getMessage(), 900)]);

            return false;
        }
    }

    public function pdf(MinimediaStatement $statement): string
    {
        return $this->pdf->render('pdf.minimedia', self::view($statement) + ['generatedAt' => now()->timezone(MinimediaCommission::TZ)]);
    }

    /** CSV para la hoja de cálculo: una fila por tienda y el total (separado por «;», euros con coma). */
    public static function csv(MinimediaStatement $statement): string
    {
        $eur = fn (int $cents): string => number_format($cents / 100, 2, ',', '');
        $rows = [['Mes', 'Tienda', 'Pedidos', 'Ventas sin IVA', 'Devoluciones sin IVA', 'Neto sin IVA', 'Porcentaje', 'Comisión Minimedia']];
        foreach ($statement->stores as $s) {
            $rows[] = [$statement->month, $s['store'], (string) $s['orders'], $eur($s['sales_base']), $eur($s['refunds_base']), $eur($s['net_base']), self::rate($statement->rate_bps), $eur($s['commission'])];
        }
        $rows[] = [$statement->month, 'TOTAL', (string) $statement->orders, $eur($statement->sales_base_cents), $eur($statement->refunds_base_cents), $eur($statement->net_base_cents), self::rate($statement->rate_bps), $eur($statement->commission_cents)];

        return "\u{FEFF}".implode("\r\n", array_map(fn (array $r): string => implode(';', array_map(fn (string $v): string => '"'.str_replace('"', '""', $v).'"', $r)), $rows))."\r\n";
    }

    /**
     * Datos ya formateados para el email y el PDF.
     *
     * @return array<string, mixed>
     */
    public static function view(MinimediaStatement $statement): array
    {
        $m = fn (int $cents): string => Money::fromCents($cents)->format();

        return [
            'label' => $statement->label(),
            'rate' => self::rate($statement->rate_bps),
            'orders' => $statement->orders,
            'sales' => $m($statement->sales_base_cents),
            'refunds' => $m($statement->refunds_base_cents),
            'net' => $m($statement->net_base_cents),
            'commission' => $m($statement->commission_cents),
            'stores' => array_map(fn (array $s): array => [
                'store' => $s['store'], 'orders' => $s['orders'], 'sales' => $m($s['sales_base']),
                'refunds' => $m($s['refunds_base']), 'net' => $m($s['net_base']), 'commission' => $m($s['commission']),
            ], $statement->stores),
        ];
    }

    public static function rate(int $bps): string
    {
        return rtrim(rtrim(number_format($bps / 100, 2, ',', ''), '0'), ',').' %';
    }
}
