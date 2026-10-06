<?php

namespace App\Http\Controllers;

use App\Models\ApplicationSetting;
use App\Services\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class ReportExportController extends Controller
{
    public function cashExcel(Request $request, ReportService $reports): BinaryFileResponse
    {
        [$from, $to] = $this->period($request);
        $report = $reports->cashReport($from, $to);
        $appSettings = ApplicationSetting::current();
        $spreadsheet = new Spreadsheet;
        $summary = $spreadsheet->getActiveSheet();
        $summary->setTitle('Ringkasan');
        $this->sheetTitle($summary, 'Laporan Kas '.$appSettings->app_name, $from, $to, 'E');
        $summary->fromArray([
            ['Total pemasukan', $report['income']],
            ['Total pengeluaran', $report['expense']],
            ['Saldo periode', $report['balance']],
        ], null, 'A5');
        $summary->getStyle('A5:A7')->getFont()->setBold(true);
        $summary->getStyle('B5:B7')->getNumberFormat()->setFormatCode('Rp #,##0;[Red]-Rp #,##0');
        $summary->fromArray(['Kategori', 'Jenis', 'Total'], null, 'A10');
        $row = 11;
        foreach ($report['categoryTotals'] as $category) {
            $summary->setCellValue("A{$row}", ($category->icon ? $category->icon.' ' : '').$category->name);
            $summary->setCellValue("B{$row}", $category->type === 'income' ? 'Pemasukan' : 'Pengeluaran');
            $summary->setCellValue("C{$row}", (int) $category->total);
            $row++;
        }
        $this->tableHeader($summary, 'A10:C10');
        $summary->getStyle('C11:C'.max(11, $row - 1))->getNumberFormat()->setFormatCode('Rp #,##0');

        $detail = $spreadsheet->createSheet();
        $detail->setTitle('Transaksi');
        $this->sheetTitle($detail, 'Rincian Transaksi Kas', $from, $to, 'F');
        $detail->fromArray(['Tanggal', 'Kategori', 'Jenis', 'Keterangan', 'Sumber', 'Nominal'], null, 'A5');
        $row = 6;
        foreach ($report['transactions'] as $transaction) {
            $detail->setCellValue("A{$row}", Date::PHPToExcel($transaction->transaction_date));
            $detail->setCellValue("B{$row}", $transaction->cashCategory->name);
            $detail->setCellValue("C{$row}", $transaction->cashCategory->type === 'income' ? 'Pemasukan' : 'Pengeluaran');
            $detail->setCellValue("D{$row}", $transaction->description ?? '');
            $detail->setCellValue("E{$row}", $transaction->play_session_member_id ? 'Iuran otomatis' : 'Manual');
            $detail->setCellValue("F{$row}", (int) $transaction->amount);
            $row++;
        }
        $this->tableHeader($detail, 'A5:F5');
        $detail->getStyle('A6:A'.max(6, $row - 1))->getNumberFormat()->setFormatCode('dd mmmm yyyy');
        $detail->getStyle('F6:F'.max(6, $row - 1))->getNumberFormat()->setFormatCode('Rp #,##0');
        $this->finishWorkbook($spreadsheet);

        return $this->downloadSpreadsheet($spreadsheet, 'laporan-kas-'.$from.'_sampai_'.$to.'.xlsx');
    }

    public function sessionsExcel(Request $request, ReportService $reports): BinaryFileResponse
    {
        [$from, $to] = $this->period($request);
        $sessionReports = $reports->sessionReport($from, $to);
        $appSettings = ApplicationSetting::current();
        $spreadsheet = new Spreadsheet;
        $summary = $spreadsheet->getActiveSheet();
        $summary->setTitle('Ringkasan Sesi');
        $this->sheetTitle($summary, 'Laporan Sesi '.$appSettings->app_name, $from, $to, 'G');
        $summary->fromArray(['Tanggal', 'Hadir', 'Admin Bertugas', 'Sudah Bayar', 'Belum Bayar', 'Iuran Diterima', 'Jumlah Game'], null, 'A5');
        $row = 6;
        foreach ($sessionReports as $report) {
            $summary->setCellValue("A{$row}", Date::PHPToExcel($report['session']->play_date));
            $summary->setCellValue("B{$row}", $report['attendance_count']);
            $summary->setCellValue("C{$row}", $report['duty_admin_count']);
            $summary->setCellValue("D{$row}", $report['paid_count']);
            $summary->setCellValue("E{$row}", $report['unpaid_count']);
            $summary->setCellValue("F{$row}", $report['dues_received']);
            $summary->setCellValue("G{$row}", $report['games']->count());
            $row++;
        }
        $this->tableHeader($summary, 'A5:G5');
        $summary->getStyle('A6:A'.max(6, $row - 1))->getNumberFormat()->setFormatCode('dd mmmm yyyy');
        $summary->getStyle('F6:F'.max(6, $row - 1))->getNumberFormat()->setFormatCode('Rp #,##0');

        $attendance = $spreadsheet->createSheet();
        $attendance->setTitle('Kehadiran');
        $this->sheetTitle($attendance, 'Kehadiran dan Pembayaran', $from, $to, 'D');
        $attendance->fromArray(['Tanggal', 'Member', 'Status Bayar', 'Iuran'], null, 'A5');
        $row = 6;
        foreach ($sessionReports as $report) {
            foreach ($report['attendances'] as $presence) {
                $attendance->setCellValue("A{$row}", Date::PHPToExcel($report['session']->play_date));
                $attendance->setCellValue("B{$row}", $presence->member?->name ?? 'Member terhapus');
                $attendance->setCellValue("C{$row}", $presence->is_duty_admin ? 'Admin bertugas - bebas iuran' : ($presence->paid_at ? 'Sudah bayar' : 'Belum bayar'));
                $attendance->setCellValue("D{$row}", $presence->is_duty_admin ? 0 : $presence->fee_amount);
                $row++;
            }
        }
        $this->tableHeader($attendance, 'A5:D5');
        $attendance->getStyle('A6:A'.max(6, $row - 1))->getNumberFormat()->setFormatCode('dd mmmm yyyy');
        $attendance->getStyle('D6:D'.max(6, $row - 1))->getNumberFormat()->setFormatCode('Rp #,##0');

        $games = $spreadsheet->createSheet();
        $games->setTitle('Game');
        $this->sheetTitle($games, 'Daftar Game dan Pemenang', $from, $to, 'G');
        $games->fromArray(['Tanggal', 'Game', 'Pasangan A', 'Pasangan B', 'Pemenang', 'Skor', 'Status'], null, 'A5');
        $row = 6;
        foreach ($sessionReports as $report) {
            foreach ($report['games'] as $game) {
                $teamA = $game->gamePlayers->where('team', 'A')->pluck('member.name')->filter()->join(' & ');
                $teamB = $game->gamePlayers->where('team', 'B')->pluck('member.name')->filter()->join(' & ');
                $games->setCellValue("A{$row}", Date::PHPToExcel($report['session']->play_date));
                $games->setCellValue("B{$row}", $game->game_number);
                $games->setCellValue("C{$row}", $teamA);
                $games->setCellValue("D{$row}", $teamB);
                $games->setCellValue("E{$row}", $game->resultLabel());
                $games->setCellValueExplicit("F{$row}", $game->scoreSummary(), DataType::TYPE_STRING);
                $games->setCellValue("G{$row}", ucfirst($game->status));
                $row++;
            }
        }
        $this->tableHeader($games, 'A5:G5');
        $games->getStyle('A6:A'.max(6, $row - 1))->getNumberFormat()->setFormatCode('dd mmmm yyyy');

        $counts = $spreadsheet->createSheet();
        $counts->setTitle('Jumlah Main');
        $this->sheetTitle($counts, 'Jumlah Main per Member', $from, $to, 'C');
        $counts->fromArray(['Tanggal Sesi', 'Member', 'Jumlah Game'], null, 'A5');
        $row = 6;
        foreach ($sessionReports as $report) {
            foreach ($report['member_game_counts'] as $memberCount) {
                $counts->setCellValue("A{$row}", Date::PHPToExcel($report['session']->play_date));
                $counts->setCellValue("B{$row}", $memberCount['member']);
                $counts->setCellValue("C{$row}", $memberCount['count']);
                $row++;
            }
        }
        $this->tableHeader($counts, 'A5:C5');
        $counts->getStyle('A6:A'.max(6, $row - 1))->getNumberFormat()->setFormatCode('dd mmmm yyyy');
        $this->finishWorkbook($spreadsheet);

        return $this->downloadSpreadsheet($spreadsheet, 'laporan-sesi-'.$from.'_sampai_'.$to.'.xlsx');
    }

    public function cashPdf(Request $request, ReportService $reports): Response
    {
        [$from, $to] = $this->period($request);

        return Pdf::loadView('exports.cash-report', [
            'report' => $reports->cashReport($from, $to),
            'from' => $from,
            'to' => $to,
        ])->setPaper('a4', 'portrait')->download('laporan-kas-'.$from.'_sampai_'.$to.'.pdf');
    }

    public function sessionsPdf(Request $request, ReportService $reports): Response
    {
        [$from, $to] = $this->period($request);

        return Pdf::loadView('exports.session-report', [
            'sessionReports' => $reports->sessionReport($from, $to),
            'from' => $from,
            'to' => $to,
        ])->setPaper('a4', 'landscape')->download('laporan-sesi-'.$from.'_sampai_'.$to.'.pdf');
    }

    /** @return array{string, string} */
    private function period(Request $request): array
    {
        $validated = validator($request->query(), [
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ])->validate();

        return [Carbon::parse($validated['from'])->toDateString(), Carbon::parse($validated['to'])->toDateString()];
    }

    private function sheetTitle(Worksheet $sheet, string $title, string $from, string $to, string $lastColumn): void
    {
        $sheet->setCellValue('A2', $title);
        $sheet->setCellValue('A3', 'Periode '.Carbon::parse($from)->translatedFormat('d F Y').' sampai '.Carbon::parse($to)->translatedFormat('d F Y'));
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(16)->getColor()->setRGB('123C2C');
        $sheet->getStyle('A3')->getFont()->setItalic(true)->getColor()->setRGB('65716A');
        $sheet->mergeCells("A2:{$lastColumn}2");
        $sheet->mergeCells("A3:{$lastColumn}3");
    }

    private function tableHeader(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '194F3A']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
    }

    private function finishWorkbook(Spreadsheet $spreadsheet): void
    {
        $spreadsheet->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $sheet->setShowGridlines(false);
            $sheet->freezePane('A6');
            foreach (range('A', $sheet->getHighestColumn()) as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }
        }
        $spreadsheet->setActiveSheetIndex(0);
    }

    private function downloadSpreadsheet(Spreadsheet $spreadsheet, string $filename): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'fuma-report-');
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return response()->download($path, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }
}
