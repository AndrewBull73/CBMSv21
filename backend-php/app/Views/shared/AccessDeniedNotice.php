<?php declare(strict_types=1); ?>
<?php
$noticeTitle = trim((string)($noticeTitle ?? 'Access Restricted'));
$noticeText = trim((string)($noticeText ?? 'You do not currently have access to this screen or function.'));
$noticeRequirement = trim((string)($noticeRequirement ?? ''));
$noticeMissingPermissions = is_array($noticeMissingPermissions ?? null) ? $noticeMissingPermissions : [];
$noticeMissingPermissions = array_values(array_unique(array_filter(array_map(
    static fn ($code): string => strtoupper(trim((string) $code)),
    $noticeMissingPermissions
))));
$noticeVariant = (string)($noticeVariant ?? 'default');
$compact = $noticeVariant === 'compact';
?>
<div class="cbms-access-denied <?= $compact ? 'cbms-access-denied-compact' : '' ?>" role="alert" aria-live="polite">
    <div class="cbms-access-denied-icon">
        <i class="bi bi-shield-lock"></i>
    </div>
    <div class="cbms-access-denied-body">
        <div class="cbms-access-denied-title"><?= htmlspecialchars($noticeTitle, ENT_QUOTES, 'UTF-8') ?></div>
        <div class="cbms-access-denied-text"><?= htmlspecialchars($noticeText, ENT_QUOTES, 'UTF-8') ?></div>
        <?php if ($noticeMissingPermissions !== []): ?>
            <div class="cbms-access-denied-missing">
                <span class="cbms-access-denied-missing-label">
                    <?= count($noticeMissingPermissions) === 1 ? 'Missing permission:' : 'Missing one of these permissions:' ?>
                </span>
                <span class="cbms-access-denied-missing-codes">
                    <?php foreach ($noticeMissingPermissions as $code): ?>
                        <code><?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?></code>
                    <?php endforeach; ?>
                </span>
            </div>
        <?php endif; ?>
        <?php if ($noticeRequirement !== ''): ?>
            <div class="cbms-access-denied-requirement"><?= htmlspecialchars($noticeRequirement, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
    </div>
</div>
<style>
    .cbms-access-denied {
        display: flex;
        gap: .9rem;
        align-items: flex-start;
        margin: 1rem;
        padding: 1rem 1.1rem;
        border: 1px solid #f3d08b;
        border-radius: .95rem;
        background: linear-gradient(135deg, #fff9e9 0%, #fffdf6 100%);
        color: #6f4e00;
        box-shadow: 0 .3rem .9rem rgba(120, 91, 0, 0.08);
    }
    .cbms-access-denied-compact {
        margin: .9rem;
        padding: .9rem 1rem;
    }
    .cbms-access-denied-icon {
        flex: 0 0 auto;
        width: 2rem;
        height: 2rem;
        border-radius: 999px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: rgba(255, 193, 7, 0.18);
        color: #9a6700;
        font-size: 1rem;
    }
    .cbms-access-denied-title {
        font-size: .92rem;
        font-weight: 700;
        letter-spacing: -.01em;
        margin-bottom: .2rem;
    }
    .cbms-access-denied-text {
        font-size: .88rem;
        line-height: 1.4;
    }
    .cbms-access-denied-missing {
        display: flex;
        flex-wrap: wrap;
        gap: .35rem .45rem;
        align-items: center;
        margin-top: .5rem;
        font-size: .8rem;
    }
    .cbms-access-denied-missing-label {
        font-weight: 700;
        color: #6f4e00;
    }
    .cbms-access-denied-missing-codes {
        display: inline-flex;
        flex-wrap: wrap;
        gap: .25rem;
    }
    .cbms-access-denied-missing code {
        padding: .12rem .35rem;
        border: 1px solid rgba(154, 103, 0, .25);
        border-radius: .35rem;
        background: rgba(255, 255, 255, .72);
        color: #6f4e00;
        font-size: .76rem;
    }
    .cbms-access-denied-requirement {
        margin-top: .45rem;
        font-size: .8rem;
        color: #8a6a16;
    }
</style>
