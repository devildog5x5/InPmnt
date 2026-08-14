<?php
declare(strict_types=1);

final class Workspace
{
    public const STARTER_OPEN_INVOICE_LIMIT = 40;

    public static function requireId(): int
    {
        $user = $GLOBALS['inpmnt_user'] ?? null;
        if (!$user || empty($user['workspace_id'])) {
            throw new RuntimeException('No workspace on user');
        }
        return (int) $user['workspace_id'];
    }

    public static function settings(PDO $db, ?int $wid = null): ?array
    {
        $wid = $wid ?? self::requireId();
        $st = $db->prepare('SELECT * FROM settings WHERE id = ?');
        $st->execute([$wid]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public static function effectivePlan(?array $settings): string
    {
        if (!$settings) {
            return 'trial';
        }
        $plan = strtolower((string) ($settings['plan'] ?? 'trial'));
        $trialEnds = (string) ($settings['trial_ends_on'] ?? '');
        if ($plan === 'trial' && $trialEnds !== '' && $trialEnds < date('Y-m-d')) {
            return 'expired';
        }
        return $plan;
    }

    public static function allowsSms(string $plan): bool
    {
        return $plan === 'pro';
    }

    public static function openInvoiceLimit(string $plan): ?int
    {
        if (in_array($plan, ['starter', 'annual', 'trial'], true)) {
            return self::STARTER_OPEN_INVOICE_LIMIT;
        }
        if ($plan === 'pro') {
            return null;
        }
        if ($plan === 'expired') {
            return 0;
        }
        return self::STARTER_OPEN_INVOICE_LIMIT;
    }

    public static function countOpen(PDO $db, int $wid): int
    {
        $st = $db->prepare(
            "SELECT COUNT(*) FROM invoices WHERE workspace_id = ? AND status IN ('sent','partial','overdue')"
        );
        $st->execute([$wid]);
        return (int) $st->fetchColumn();
    }

    public static function assertCanAddOpen(PDO $db, int $wid, ?array $settings): ?string
    {
        $plan = self::effectivePlan($settings);
        if ($plan === 'expired') {
            return 'Your trial has ended. Subscribe on the Billing page to continue.';
        }
        $limit = self::openInvoiceLimit($plan);
        if ($limit === null) {
            return null;
        }
        if (self::countOpen($db, $wid) >= $limit) {
            return "Open invoice limit reached ({$limit}). Upgrade to Pro for unlimited open invoices.";
        }
        return null;
    }
}
