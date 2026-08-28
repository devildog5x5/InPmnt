<?php
declare(strict_types=1);

/** One-page letter invoice PDF. Helvetica + optional JPEG logo. No Composer. */
final class InvoicePdf
{
    /** @param array<string,mixed> $data */
    public static function build(array $data): string
    {
        $W = 612.0;
        $H = 792.0;
        $ops = [];
        $fill = static function (float $x, float $y, float $w, float $h, string $hex) use (&$ops): void {
            $ops[] = self::rgb($hex) . sprintf(' rg %.2f %.2f %.2f %.2f re f', $x, $y, $w, $h);
        };
        $text = static function (float $x, float $y, string $s, float $size = 10.0, bool $bold = false, string $color = '15202b') use (&$ops): void {
            $font = $bold ? 'F2' : 'F1';
            $ops[] = sprintf(
                'BT /%s %.1f Tf %s rg 1 0 0 1 %.2f %.2f Tm (%s) Tj ET',
                $font,
                $size,
                self::rgb($color),
                $x,
                $y,
                self::esc($s)
            );
        };
        $textRight = static function (float $xr, float $y, string $s, float $size = 10.0, bool $bold = false, string $color = '15202b') use ($text): void {
            $tw = self::width($s, $size, $bold);
            $text($xr - $tw, $y, $s, $size, $bold, $color);
        };

        $biz = (string) ($data['business_name'] ?? 'InPmnt');
        $number = (string) ($data['number'] ?? 'INV-0000');
        $client = (string) ($data['client_name'] ?? 'Client');
        $company = (string) ($data['client_company'] ?? '');
        $clientEmail = (string) ($data['client_email'] ?? '');
        $clientPhone = (string) ($data['client_phone'] ?? '');
        $title = (string) ($data['title'] ?? 'Services');
        $notes = (string) ($data['notes'] ?? '');
        $issue = (string) ($data['issue_date'] ?? '');
        $due = (string) ($data['due_date'] ?? '');
        $status = strtoupper((string) ($data['status'] ?? 'sent'));
        $amount = (string) ($data['amount'] ?? '$0.00');
        $paid = (string) ($data['amount_paid'] ?? '$0.00');
        $dueAmt = (string) ($data['amount_due'] ?? $amount);
        $owner = (string) ($data['owner_name'] ?? '');
        $bizEmail = (string) ($data['business_email'] ?? '');
        $bizPhone = (string) ($data['business_phone'] ?? '');
        $website = (string) ($data['website'] ?? '');
        $currency = (string) ($data['currency'] ?? 'USD');

        $fill(0, $H - 8, $W, 8, '0d6b66');

        $jpeg = self::logoJpeg();
        $imgW = 160;
        $imgH = 160;
        $logoPt = 88.0;
        $logoX = 40.0;
        $logoY = 680.0;
        if ($jpeg !== null) {
            [$imgW, $imgH] = self::jpegSize($jpeg);
            $ops[] = sprintf('q %.2f 0 0 %.2f %.2f %.2f cm /Im1 Do Q', $logoPt, $logoPt, $logoX, $logoY);
        }
        $textX = $jpeg !== null ? ($logoX + $logoPt + 16) : 48.0;
        $text($textX, 752, $biz, 16, true);
        $yInfo = 732.0;
        foreach ([$owner, $bizEmail, $bizPhone, $website] as $line) {
            if ($line !== '') {
                $text($textX, $yInfo, $line, 9, false, '2a3a48');
                $yInfo -= 12;
            }
        }
        $textRight(564, 752, 'INVOICE', 22, true, '0d6b66');
        $textRight(564, 732, $number, 12, true);
        $textRight(564, 716, $status, 8, true, '1a8a84');
        $fill(40, 664, 532, 1.0, 'e2e8ee');

        $yTo = 640.0;
        $text(48, $yTo, 'BILL TO', 8, true, '667888');
        $yTo -= 16;
        $text(48, $yTo, $client, 12, true);
        $yTo -= 14;
        if ($company !== '') {
            $text(48, $yTo, $company, 9, false, '2a3a48');
            $yTo -= 12;
        }
        foreach ([$clientEmail, $clientPhone] as $line) {
            if ($line !== '') {
                $text(48, $yTo, $line, 9, false, '2a3a48');
                $yTo -= 12;
            }
        }

        $fill(48, 538, 516, 52, 'f6f8fa');
        $text(64, 572, 'Issued', 8, true, '667888');
        $text(64, 554, $issue !== '' ? $issue : '—', 11, true);
        $text(220, 572, 'Due', 8, true, '667888');
        $text(220, 554, $due !== '' ? $due : '—', 11, true);
        $text(360, 572, 'Currency', 8, true, '667888');
        $text(360, 554, $currency, 11, true);
        $textRight(548, 572, 'Amount due', 8, true, '667888');
        $textRight(548, 552, $dueAmt, 13, true, '0d6b66');

        $fill(48, 500, 516, 22, '0d6b66');
        $text(64, 507, 'Description', 9, true, 'ffffff');
        $textRight(548, 507, 'Amount', 9, true, 'ffffff');
        $fill(48, 468, 516, 32, 'ffffff');
        $fill(48, 468, 516, 0.6, 'e2e8ee');
        $text(64, 480, $title, 10);
        $textRight(548, 480, $amount, 10, true);

        $fill(332, 378, 232, 78, 'f6f8fa');
        $text(348, 432, 'Subtotal', 9, false, '667888');
        $textRight(548, 432, $amount, 9);
        $text(348, 414, 'Paid', 9, false, '667888');
        $textRight(548, 414, $paid, 9);
        $fill(348, 404, 200, 0.6, 'cfd8e1');
        $text(348, 388, 'Amount due', 11, true, '0d6b66');
        $textRight(548, 388, $dueAmt, 12, true, '0d6b66');

        if ($notes !== '') {
            $text(48, 432, 'Notes', 8, true, '667888');
            $ny = 416.0;
            foreach (array_slice(self::wrap($notes, 42), 0, 6) as $line) {
                $text(48, $ny, $line, 9, false, '2a3a48');
                $ny -= 12;
            }
        }

        $fill(0, 0, $W, 48, '101920');
        $text(48, 22, 'InPmnt  ·  Professional invoices & reminders', 8, false, '8a99a8');
        $textRight(564, 22, 'Thank you for your business', 8, false, '5ee0d8');

        $content = implode("\n", $ops) . "\n";
        $contentB = $content; // already ASCII/latin

        $objects = [];
        $objects[] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[] = '<< /Type /Pages /Kids [3 0 R] /Count 1 >>';
        if ($jpeg !== null) {
            $resources = '<< /Font << /F1 4 0 R /F2 5 0 R >> /XObject << /Im1 6 0 R >> >>';
            $objects[] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {$W} {$H}] /Resources {$resources} /Contents 7 0 R >>";
        } else {
            $resources = '<< /Font << /F1 4 0 R /F2 5 0 R >> >>';
            $objects[] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {$W} {$H}] /Resources {$resources} /Contents 6 0 R >>";
        }
        $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        if ($jpeg !== null) {
            $objects[] = '<< /Type /XObject /Subtype /Image /Width ' . $imgW . ' /Height ' . $imgH
                . ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ' . strlen($jpeg)
                . " >>\nstream\n" . $jpeg . 'endstream';
        }
        $objects[] = '<< /Length ' . strlen($contentB) . " >>\nstream\n" . $contentB . 'endstream';

        $out = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0];
        foreach ($objects as $i => $obj) {
            $offsets[] = strlen($out);
            $n = $i + 1;
            $out .= "{$n} 0 obj\n" . $obj;
            if (!str_ends_with($obj, "\n")) {
                $out .= "\n";
            }
            $out .= "endobj\n";
        }
        $xref = strlen($out);
        $count = count($objects) + 1;
        $out .= "xref\n0 {$count}\n0000000000 65535 f \n";
        for ($i = 1; $i < $count; $i++) {
            $out .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $out .= "trailer\n<< /Size {$count} /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
        return $out;
    }

    /** @param array<string,mixed> $inv @param array<string,mixed>|null $settings */
    public static function payload(array $inv, ?array $settings): array
    {
        $st = $settings ?? [];
        $amount = (float) ($inv['amount'] ?? 0);
        $paid = (float) ($inv['amount_paid'] ?? 0);
        $due = round($amount - $paid, 2);
        return [
            'business_name' => (string) ($st['business_name'] ?? 'InPmnt'),
            'number' => (string) ($inv['number'] ?? 'INV-0000'),
            'client_name' => (string) ($inv['client_name'] ?? 'Client'),
            'client_company' => (string) ($inv['client_company'] ?? ''),
            'client_email' => (string) ($inv['client_email'] ?? ''),
            'client_phone' => (string) ($inv['client_phone'] ?? ''),
            'title' => (string) ($inv['title'] ?? 'Services'),
            'notes' => (string) ($inv['notes'] ?? ''),
            'issue_date' => (string) ($inv['issue_date'] ?? ''),
            'due_date' => (string) ($inv['due_date'] ?? ''),
            'status' => (string) ($inv['status'] ?? 'sent'),
            'amount' => Db::money($amount),
            'amount_paid' => Db::money($paid),
            'amount_due' => Db::money($due),
            'owner_name' => (string) ($st['owner_name'] ?? ''),
            'business_email' => (string) ($st['email'] ?? ''),
            'business_phone' => (string) ($st['phone'] ?? ''),
            'website' => (string) ($st['website'] ?? ''),
            'currency' => (string) ($st['currency'] ?? 'USD'),
        ];
    }

    public static function filename(string $number): string
    {
        $safe = preg_replace('/[^A-Za-z0-9._-]+/', '_', $number) ?: 'invoice';
        $safe = trim($safe, '._') ?: 'invoice';
        if (!str_ends_with(strtolower($safe), '.pdf')) {
            $safe .= '.pdf';
        }
        return $safe;
    }

    public static function mentionAttachment(string $body): string
    {
        if (str_contains(strtolower($body), 'pdf copy of this invoice')) {
            return $body;
        }
        return rtrim($body) . "\n\nA PDF copy of this invoice is attached.\n";
    }

    /** @return list<string> */
    public static function logoCandidates(): array
    {
        $srcDir = __DIR__;
        $phpDir = dirname($srcDir);
        $repoDir = dirname($phpDir);
        $names = ['inpmnt-logo-invoice.jpg', 'inpmnt-icon.png'];
        $dirs = [
            $srcDir,
            $phpDir . '/static/img',
            $repoDir . '/static/img',
            $phpDir . '/img',
        ];
        $doc = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/\\');
        if ($doc !== '') {
            $dirs[] = $doc . '/static/img';
            $dirs[] = $doc . '/img';
        }
        $out = [];
        foreach ($dirs as $dir) {
            foreach ($names as $name) {
                $out[] = $dir . '/' . $name;
            }
        }
        return $out;
    }

    public static function logoPath(): ?string
    {
        foreach (self::logoCandidates() as $p) {
            if (is_file($p) && is_readable($p)) {
                return $p;
            }
        }
        return null;
    }

    private static function logoJpeg(): ?string
    {
        foreach (self::logoCandidates() as $p) {
            if (!is_file($p) || !is_readable($p)) {
                continue;
            }
            $raw = file_get_contents($p);
            if ($raw === false || $raw === '') {
                continue;
            }
            $ext = strtolower(pathinfo($p, PATHINFO_EXTENSION));
            if ($ext === 'png') {
                $jpeg = self::pngToJpeg($raw);
                if ($jpeg !== null) {
                    return $jpeg;
                }
                $sibling = dirname($p) . '/inpmnt-logo-invoice.jpg';
                if (is_file($sibling) && is_readable($sibling)) {
                    $alt = file_get_contents($sibling);
                    if (is_string($alt) && str_starts_with($alt, "\xFF\xD8")) {
                        return $alt;
                    }
                }
                continue;
            }
            if (str_starts_with($raw, "\xFF\xD8")) {
                return $raw;
            }
        }
        return null;
    }

    private static function pngToJpeg(string $png): ?string
    {
        if (!function_exists('imagecreatefromstring')) {
            return null;
        }
        $im = @imagecreatefromstring($png);
        if ($im === false) {
            return null;
        }
        $size = 160;
        $dst = imagecreatetruecolor($size, $size);
        $bg = imagecolorallocate($dst, 16, 25, 32);
        imagefilledrectangle($dst, 0, 0, $size, $size, $bg);
        imagecopyresampled($dst, $im, 0, 0, 0, 0, $size, $size, imagesx($im), imagesy($im));
        ob_start();
        imagejpeg($dst, null, 88);
        $jpeg = ob_get_clean();
        imagedestroy($im);
        imagedestroy($dst);
        return is_string($jpeg) && str_starts_with($jpeg, "\xFF\xD8") ? $jpeg : null;
    }

    /** @return array{0:int,1:int} */
    private static function jpegSize(string $jpeg): array
    {
        $len = strlen($jpeg);
        $i = 2;
        while ($i < $len - 8) {
            if (ord($jpeg[$i]) !== 0xFF) {
                break;
            }
            $marker = ord($jpeg[$i + 1]);
            if ($marker === 0xC0 || $marker === 0xC1 || $marker === 0xC2) {
                $h = (ord($jpeg[$i + 5]) << 8) | ord($jpeg[$i + 6]);
                $w = (ord($jpeg[$i + 7]) << 8) | ord($jpeg[$i + 8]);
                return [$w > 0 ? $w : 160, $h > 0 ? $h : 160];
            }
            $seglen = (ord($jpeg[$i + 2]) << 8) | ord($jpeg[$i + 3]);
            if ($seglen < 2) {
                break;
            }
            $i += 2 + $seglen;
        }
        return [160, 160];
    }

    private static function rgb(string $hex): string
    {
        $hex = ltrim($hex, '#');
        $r = hexdec(substr($hex, 0, 2)) / 255;
        $g = hexdec(substr($hex, 2, 2)) / 255;
        $b = hexdec(substr($hex, 4, 2)) / 255;
        return sprintf('%.3f %.3f %.3f', $r, $g, $b);
    }

    private static function esc(string $text): string
    {
        $text = str_replace(["\r", "\n"], ' ', $text);
        $out = '';
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($chars as $ch) {
            if ($ch === '\\' || $ch === '(' || $ch === ')') {
                $out .= '\\' . $ch;
                continue;
            }
            if ($ch === "\u{00B7}") {
                $out .= "\xB7";
                continue;
            }
            if ($ch === "\u{2014}") {
                $out .= "\x97";
                continue;
            }
            if (strlen($ch) === 1) {
                $o = ord($ch);
                $out .= ($o >= 32 && $o <= 126) ? $ch : '?';
            } else {
                $out .= '?';
            }
        }
        return $out;
    }

    private static function width(string $s, float $size, bool $bold): float
    {
        $helv = [278,278,355,556,556,889,667,191,333,333,389,584,278,333,278,278,556,556,556,556,556,556,556,556,556,556,278,278,584,584,584,556,1015,667,667,722,722,667,611,778,722,278,500,667,556,833,722,778,667,778,722,667,611,722,667,944,667,667,611,278,278,278,469,556,333,556,556,500,556,556,278,556,556,222,222,500,222,833,556,556,556,556,333,500,278,556,500,722,500,500,500,334,260,334,584,278];
        $helvB = [278,333,474,556,556,889,722,238,333,333,389,584,278,333,278,278,556,556,556,556,556,556,556,556,556,556,333,333,584,584,584,611,975,722,722,722,722,667,611,778,722,278,556,722,611,833,722,778,667,778,722,667,611,722,667,944,667,667,611,333,278,333,584,556,333,556,611,556,611,556,333,611,611,278,278,556,278,889,611,611,611,611,389,556,333,611,556,778,556,556,500,389,280,389,584,278];
        $table = $bold ? $helvB : $helv;
        $w = 0.0;
        $chars = preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($chars as $ch) {
            $o = strlen($ch) === 1 ? ord($ch) : 46;
            if ($o < 32 || $o > 126) {
                $o = 46;
            }
            $w += $table[$o - 32] * $size / 1000.0;
        }
        return $w;
    }

    /** @return list<string> */
    private static function wrap(string $text, int $width): array
    {
        $words = preg_split('/\s+/', trim(str_replace(["\r", "\n"], ' ', $text))) ?: [];
        $lines = [];
        $cur = '';
        foreach ($words as $word) {
            $trial = trim($cur . ' ' . $word);
            if (strlen($trial) <= $width) {
                $cur = $trial;
            } else {
                if ($cur !== '') {
                    $lines[] = $cur;
                }
                $cur = $word;
            }
        }
        if ($cur !== '') {
            $lines[] = $cur;
        }
        return $lines ?: [''];
    }
}
