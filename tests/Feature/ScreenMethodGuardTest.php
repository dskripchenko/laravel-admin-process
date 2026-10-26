<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminProcess\Tests\Feature;

use Dskripchenko\LaravelAdminProcess\Permissions;
use Dskripchenko\LaravelAdminProcess\Screens\ProcessInstanceScreen;
use Dskripchenko\LaravelAdminProcess\Tests\TestCase;

/**
 * A screen method no action names is open to anyone who sees the page.
 * Every public method of the instance page but the read-only ones must be
 * refused to a viewer.
 */
final class ScreenMethodGuardTest extends TestCase
{
    private const READ_ONLY = ['flowState', 'subprocess'];

    public function test_every_operator_method_is_refused_to_a_viewer(): void
    {
        $this->actingAsAdmin(permissions: [Permissions::VIEW]);
        $id = $this->order();
        $screen = new ProcessInstanceScreen;

        $methods = [];
        foreach ((new \ReflectionClass(ProcessInstanceScreen::class))->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            $name = $method->getName();
            if ($method->isStatic() || $method->getDeclaringClass()->getName() !== ProcessInstanceScreen::class
                || in_array($name, self::READ_ONLY, true) || ! $screen->isCallableMethod($name)) {
                continue;
            }
            $methods[] = $name;
            $this->postJson('/api/admin/process-instance/runMethod', [
                'method' => $name,
                'payload' => ['instance_id' => $id, 'reason' => 'probe'],
            ])->assertForbidden();
        }

        $this->assertContains('transition', $methods);
        $this->assertSame('running', $this->engine()->get($id)->status());
    }
}
