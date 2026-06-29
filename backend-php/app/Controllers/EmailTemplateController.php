<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\EmailTemplateModel;
use App\Shared\SessionHelper;

require_once __DIR__ . '/../../shared/csrf.php';

final class EmailTemplateController extends BaseController
{
    protected array $acl = [
        '*' => ['auth' => true, 'permsAny' => ['USERS_ADMIN', 'SYSADMIN', 'ADMIN_ALL']],
        'list' => ['auth' => true, 'permsAny' => ['USERS_ADMIN', 'SYSADMIN', 'ADMIN_ALL']],
        'form' => ['auth' => true, 'permsAny' => ['USERS_ADMIN', 'SYSADMIN', 'ADMIN_ALL']],
        'save' => ['auth' => true, 'permsAny' => ['USERS_ADMIN', 'SYSADMIN', 'ADMIN_ALL']],
        'setActive' => ['auth' => true, 'permsAny' => ['USERS_ADMIN', 'SYSADMIN', 'ADMIN_ALL']],
    ];

    public function list(): void
    {
        require __DIR__ . '/../../config/db.php';
        $model = new EmailTemplateModel($conn);
        $tableInstalled = $model->supportsEmailTemplates();

        $this->render('emailtemplates/List', [
            'title' => 'Email Templates',
            'rows' => $tableInstalled ? $model->list(false) : [],
            'tableInstalled' => $tableInstalled,
            'installScript' => 'backend-php/config/sql/create_email_templates.sql',
            'lastError' => $model->getLastError(),
        ]);
    }

    public function form(): void
    {
        require __DIR__ . '/../../config/db.php';
        $model = new EmailTemplateModel($conn);
        $tableInstalled = $model->supportsEmailTemplates();

        $id = (int)($_GET['id'] ?? 0);
        $row = $id > 0 ? $model->findById($id) : null;
        if ($id > 0 && !$row) {
            $this->flashError('Email template not found.');
            header('Location: index.php?route=email-templates/list');
            exit;
        }

        $this->render('emailtemplates/Form', [
            'title' => $id > 0 ? 'Edit Email Template' : 'Create Email Template',
            'row' => $row,
            'tableInstalled' => $tableInstalled,
            'installScript' => 'backend-php/config/sql/create_email_templates.sql',
            'availableTokens' => $this->availableTokens(),
        ]);
    }

    public function save(): void
    {
        $this->assertPostWithCsrf('index.php?route=email-templates/list');

        require __DIR__ . '/../../config/db.php';
        $model = new EmailTemplateModel($conn);
        $id = (int)($_POST['EmailTemplateID'] ?? 0);

        try {
            $savedId = $model->save([
                'EmailTemplateID' => $id,
                'TemplateKey' => $_POST['TemplateKey'] ?? '',
                'TemplateName' => $_POST['TemplateName'] ?? '',
                'Description' => $_POST['Description'] ?? '',
                'Subject' => $_POST['Subject'] ?? '',
                'BodyHtml' => $_POST['BodyHtml'] ?? '',
                'BodyText' => $_POST['BodyText'] ?? '',
                'Active' => isset($_POST['Active']) ? 1 : 0,
            ], (int)SessionHelper::get('auth.user_id', 0));

            $this->auditEvent($id > 0 ? 'UPDATE' : 'CREATE', 'EmailTemplate', $savedId, [
                'TemplateKey' => $_POST['TemplateKey'] ?? '',
                'TemplateName' => $_POST['TemplateName'] ?? '',
            ]);
            $this->flashSuccess('Email template saved.');
            header('Location: index.php?route=email-templates/list');
            exit;
        } catch (\Throwable $e) {
            $this->logHandledException('EmailTemplateController::save failed', $e, [
                'templateId' => $id,
                'templateKey' => $_POST['TemplateKey'] ?? '',
            ]);
            $this->flashError('Email template save failed: ' . $e->getMessage());
            $redirect = 'index.php?route=email-templates/form';
            if ($id > 0) {
                $redirect .= '&id=' . $id;
            }
            header('Location: ' . $redirect);
            exit;
        }
    }

    public function setActive(): void
    {
        $this->assertPostWithCsrf('index.php?route=email-templates/list');

        require __DIR__ . '/../../config/db.php';
        $id = (int)($_POST['EmailTemplateID'] ?? 0);
        $active = (int)($_POST['Active'] ?? 0) === 1;

        if ($id <= 0) {
            $this->flashError('Invalid email template.');
            header('Location: index.php?route=email-templates/list');
            exit;
        }

        try {
            $model = new EmailTemplateModel($conn);
            $model->setActive($id, $active, (int)SessionHelper::get('auth.user_id', 0));
            $this->auditEvent($active ? 'ENABLE' : 'DISABLE', 'EmailTemplate', $id);
            $this->flashSuccess($active ? 'Email template enabled.' : 'Email template disabled.');
        } catch (\Throwable $e) {
            $this->logHandledException('EmailTemplateController::setActive failed', $e, [
                'templateId' => $id,
                'active' => $active,
            ]);
            $this->flashError('Email template status update failed: ' . $e->getMessage());
        }

        header('Location: index.php?route=email-templates/list');
        exit;
    }

    /**
     * @return array<string, string>
     */
    private function availableTokens(): array
    {
        return [
            '{{APP_NAME}}' => 'Application name shown to the user.',
            '{{USERNAME}}' => 'The new user login name.',
            '{{DISPLAY_NAME}}' => 'The user display name, or username when no display name is set.',
            '{{FIRST_NAME}}' => 'The user first name.',
            '{{LAST_NAME}}' => 'The user last name.',
            '{{EMAIL}}' => 'The user email address.',
            '{{CBMS_LOGIN_URL}}' => 'The standard login page URL.',
            '{{CBMS_LOGIN_LINK}}' => 'HTML link to the standard login page.',
            '{{CBMS_SECURE_LOGIN_URL}}' => 'One-time secure login URL.',
            '{{CBMS_SECURE_LOGIN_LINK}}' => 'HTML link to the one-time secure login URL.',
            '{{EXPIRES_AT}}' => 'Expiry timestamp for the secure login link.',
            '{{EXPIRES_MINUTES}}' => 'Secure login link lifetime in minutes.',
        ];
    }
}
