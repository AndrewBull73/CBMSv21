<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Shared\SessionHelper;
use App\Models\ExampleModel;
use App\Models\AuditModel;

final class ExampleController extends BaseController
{
    protected array $acl = [
        '*'       => ['auth' => true, 'permsAny' => ['EXAMPLE_ADMIN']],
        'list'    => ['auth' => true, 'permsAny' => ['EXAMPLE_VIEW','EXAMPLE_ADMIN']],
        'edit'    => ['auth' => true, 'permsAny' => ['EXAMPLE_EDIT','EXAMPLE_ADMIN']],
        'save'    => ['auth' => true, 'permsAny' => ['EXAMPLE_EDIT','EXAMPLE_ADMIN']],
        'delete'  => ['auth' => true, 'permsAny' => ['EXAMPLE_ADMIN']],
    ];

    public function __construct()
    {
        parent::__construct();
    }

    /** List records */
    public function list(): void
    {
        require __DIR__ . '/../../config/db.php';
        $model = new ExampleModel($conn);

        $q       = trim((string)($_GET['q'] ?? ''));
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 10;

        $totalCount = $model->countFiltered($q);
        $records    = $model->listFiltered($q, $page, $perPage);
        $totalPages = (int)ceil($totalCount / $perPage);

        $flash = SessionHelper::get('flash.message', null);

        $this->render('example/ExampleList', [
            'title'       => __t('menu_example'),
            'records'     => $records,
            'currentPage' => $page,
            'totalPages'  => $totalPages,
            'totalCount'  => $totalCount,
            'q'           => $q,
            'flash'       => $flash,
        ]);

        if ($flash) SessionHelper::forget('flash.message');
    }

    /** Edit */
    public function edit(): void
    {
        require __DIR__ . '/../../config/db.php';
        $id    = (int)($_GET['id'] ?? 0);
        $model = new ExampleModel($conn);
        $record= $id > 0 ? $model->find($id) : null;

        $flash = SessionHelper::get('flash.message', null);

        $this->render('example/ExampleForm', [
            'title'  => $id > 0 ? __t('edit_example') : __t('create_example'),
            'record' => $record,
            'flash'  => $flash,
        ]);

        if ($flash) SessionHelper::forget('flash.message');
    }

    /** Save */
    public function save(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            echo __t('method_not_allowed');
            return;
        }

        require __DIR__ . '/../../config/db.php';
        $model = new ExampleModel($conn);
        $audit = new AuditModel($conn);

        $id = (int)($_POST['ExampleID'] ?? 0);
        $data = [
            'Name'        => trim((string)($_POST['Name'] ?? '')),
            'Description' => trim((string)($_POST['Description'] ?? '')),
        ];

        try {
            if ($id > 0) {
                $model->update($id, $data);
                $this->flashSuccess(__t('example_updated'));

                $audit->insert([
                    'UserID'    => SessionHelper::get('auth.user_id'),
                    'Username'  => SessionHelper::get('auth.username', 'guest'),
                    'Action'    => 'UPDATE',
                    'Entity'    => 'Example',
                    'EntityKey' => (string)$id,
                    'Details'   => $data,
                ]);
            } else {
                $model->create($data);
                $this->flashSuccess(__t('example_created'));

                $audit->insert([
                    'UserID'    => SessionHelper::get('auth.user_id'),
                    'Username'  => SessionHelper::get('auth.username', 'guest'),
                    'Action'    => 'CREATE',
                    'Entity'    => 'Example',
                    'EntityKey' => $data['Name'],
                    'Details'   => $data,
                ]);
            }
        } catch (\Throwable $e) {
            $this->flashError(__t('example_save_failed') . ': ' . $e->getMessage());
            app_log('example.save.error: ' . $e->getMessage(), ['trace'=>$e->getTraceAsString()], 'error');
        }

        header('Location: index.php?route=example/list');
        exit;
    }

    /** Delete */
    public function delete(): void
    {
        require __DIR__ . '/../../config/db.php';
        $model = new ExampleModel($conn);
        $audit = new AuditModel($conn);

        $id = (int)($_GET['id'] ?? 0);

        try {
            $model->delete($id);
            $this->flashSuccess(__t('example_deleted'));

            $audit->insert([
                'UserID'    => SessionHelper::get('auth.user_id'),
                'Username'  => SessionHelper::get('auth.username', 'guest'),
                'Action'    => 'DELETE',
                'Entity'    => 'Example',
                'EntityKey' => (string)$id,
                'Details'   => ['Message' => "Example $id deleted"],
            ]);
        } catch (\Throwable $e) {
            $this->flashError(__t('example_delete_failed') . ': ' . $e->getMessage());
        }

        header('Location: index.php?route=example/list');
        exit;
    }
}
