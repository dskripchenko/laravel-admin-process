<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminProcess\Tests\Feature;

use Dskripchenko\LaravelAdmin\Admin;
use Dskripchenko\LaravelAdminProcess\AdminProcessPlugin;
use Dskripchenko\LaravelAdminProcess\Permissions;
use Dskripchenko\LaravelAdminProcess\Resources\ProcessIncidentResource;
use Dskripchenko\LaravelAdminProcess\Resources\ProcessInstanceResource;
use Dskripchenko\LaravelAdminProcess\Tests\TestCase;

final class PluginRegistrationTest extends TestCase
{
    public function test_the_plugin_registers_its_resources_and_permissions(): void
    {
        $this->assertContains(AdminProcessPlugin::class, (array) config('admin.plugins'));

        $admin = $this->app->make(Admin::class);
        $this->assertContains(ProcessInstanceResource::class, $admin->getResources());
        $this->assertContains(ProcessIncidentResource::class, $admin->getResources());
        foreach ([Permissions::VIEW, Permissions::INCIDENTS_VIEW, Permissions::OPERATE, Permissions::MANAGE] as $key) {
            $this->assertTrue($admin->getPermissionRegistry()->knows($key), $key);
        }
    }

    public function test_the_version_comes_from_composer(): void
    {
        $this->assertNotSame('', (new AdminProcessPlugin)->version());
    }

    public function test_the_captions_are_translated(): void
    {
        $this->app->setLocale('en');
        $this->assertSame('Process instances', ProcessInstanceResource::label());
        $this->app->setLocale('ru');
        $this->assertSame('Экземпляры процессов', ProcessInstanceResource::label());
    }
}
