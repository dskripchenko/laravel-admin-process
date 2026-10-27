<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminProcess;

use Composer\InstalledVersions;
use Dskripchenko\LaravelAdmin\Admin;
use Dskripchenko\LaravelAdmin\Permission\ItemPermission;
use Dskripchenko\LaravelAdmin\Plugin\AdminPlugin;
use Dskripchenko\LaravelAdminProcess\Resources\ProcessDefinitionResource;
use Dskripchenko\LaravelAdminProcess\Resources\ProcessIncidentResource;
use Dskripchenko\LaravelAdminProcess\Resources\ProcessInstanceResource;
use Dskripchenko\LaravelAdminProcess\Screens\ProcessDefinitionScreen;
use Dskripchenko\LaravelAdminProcess\Screens\ProcessInstanceScreen;
use Dskripchenko\LaravelAdminProcess\Widgets\ProcessOverviewWidget;

final class AdminProcessPlugin implements AdminPlugin
{
    public function name(): string
    {
        return 'process';
    }

    public function version(): string
    {
        return InstalledVersions::getPrettyVersion('dskripchenko/laravel-admin-process') ?? 'dev';
    }

    public function register(): void {}

    public function boot(Admin $admin): void
    {
        $admin->resources([ProcessDefinitionResource::class, ProcessInstanceResource::class, ProcessIncidentResource::class]);
        $admin->screen(ProcessInstanceScreen::class);
        $admin->screen(ProcessDefinitionScreen::class);
        $admin->widgets([ProcessOverviewWidget::class]);

        // Source strings: core translates them per request.
        $admin->permissions(ItemPermission::group('Процессы')
            ->addPermission(Permissions::VIEW, 'Процессы: просмотр экземпляров')
            ->addPermission(Permissions::INCIDENTS_VIEW, 'Процессы: просмотр инцидентов')
            ->addPermission(Permissions::OPERATE, 'Процессы: операции (пауза, отмена, повтор, инциденты)')
            ->addPermission(Permissions::MANAGE, 'Процессы: вмешательство (перевод, контекст, исход эффекта)')
            ->addPermission(Permissions::SWITCHES, 'Процессы: выключение и включение этапов')
            ->addPermission(Permissions::START, 'Процессы: запуск вручную'));
    }
}
