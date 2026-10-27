<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminProcess\Tests\Feature;

use Dskripchenko\LaravelAdminProcess\Permissions;
use Dskripchenko\LaravelAdminProcess\Tests\Fixtures\Charge;
use Dskripchenko\LaravelAdminProcess\Tests\TestCase;
use Illuminate\Testing\TestResponse;

final class InstanceScreenTest extends TestCase
{
    public function test_the_page_shows_the_instance(): void
    {
        $this->actingAsAdmin(permissions: [Permissions::VIEW]);
        $id = $this->order();

        $response = $this->getJson('/api/admin/process-instance/state?id='.$id)->assertOk();

        $this->assertSame($id, $response->json('payload.state.instance_id'));
        $this->assertSame('new', $response->json('payload.state.summary.states'));
        $this->assertSame('new', $response->json('payload.state.summary_states'), 'an infolist entry reads a top-level key');
        $this->assertSame('New order', $response->json('payload.state.summary.label'));
        $this->assertSame(1, $response->json('payload.state.graph.version'));
        $this->assertContains('pay', array_column($response->json('payload.state.expects'), 'event'));
        $this->assertCount(1, $response->json('payload.state.timers'));
        $journal = $response->json('payload.state.journal');
        $this->assertSame('started', end($journal)['type'], 'newest first');

        $layout = $response->json('payload.layout');
        $this->assertSame('infolist', $layout[0]['type']);
        $this->assertSame('tabs', $layout[1]['type']);
    }

    public function test_an_unknown_id_says_so(): void
    {
        $this->actingAsAdmin(permissions: [Permissions::VIEW]);

        $response = $this->getJson('/api/admin/process-instance/state?id=nope')->assertOk();

        $this->assertFalse($response->json('payload.state.found'));
    }

    public function test_another_screens_id_never_reaches_the_database(): void
    {
        $this->actingAsAdmin(permissions: [Permissions::VIEW]);
        $instanceQueries = [];
        \Illuminate\Support\Facades\DB::listen(function ($query) use (&$instanceQueries): void {
            if (str_contains($query->sql, 'pe_instances') && in_array('1', array_map('strval', $query->bindings), true)) {
                $instanceQueries[] = $query->sql;
            }
        });

        $response = $this->getJson('/api/admin/process-instance/state?id=1')->assertOk();

        $this->assertFalse($response->json('payload.state.found'));
        $this->assertSame([], $instanceQueries);
    }

    public function test_the_page_needs_the_view_permission(): void
    {
        $this->actingAsAdmin(permissions: [Permissions::INCIDENTS_VIEW]);
        $id = $this->order();

        $this->getJson('/api/admin/process-instance/state?id='.$id)->assertForbidden();
    }

    public function test_the_flow_is_polled_with_the_positions(): void
    {
        $this->actingAsAdmin(permissions: [Permissions::VIEW]);
        $id = $this->order(pay: true);

        $extra = $this->method('flowState', parameters: [['path' => [], 'ref' => null, 'instance' => $id]])->assertOk()->json('payload.extra');

        $this->assertSame(['paid'], $extra['current']);
        $this->assertSame('done', $extra['nodes']['paying']['status']);
    }

    public function test_a_referenced_process_loads_by_name_and_version(): void
    {
        $this->actingAsAdmin(permissions: [Permissions::VIEW]);
        $this->order();

        $extra = $this->method('subprocess', parameters: ['order-flow@1', ['id' => 'x']])->assertOk()->json('payload.extra');

        $this->assertContains('paying', array_column($extra['nodes'], 'id'));
        $this->method('subprocess', parameters: ['missing@1', []])->assertStatus(422);
    }

    public function test_an_operator_moves_the_instance(): void
    {
        $this->actingAsAdmin(permissions: [Permissions::VIEW, Permissions::MANAGE]);
        $id = $this->order();

        $this->method('transition', payload: ['instance_id' => $id, 'state' => 'review', 'reason' => 'Manual review', 'run_exit' => true, 'run_entry' => true])
            ->assertOk()->assertJsonPath('payload.message', 'Done');

        $this->assertSame(['review'], $this->engine()->get($id)->leaves());
        $this->method('transition', payload: ['instance_id' => $id, 'state' => 'nowhere', 'reason' => 'x'])->assertStatus(422);
    }

    public function test_moving_needs_the_manage_permission(): void
    {
        $this->actingAsAdmin(permissions: [Permissions::VIEW, Permissions::OPERATE]);
        $id = $this->order();

        $this->method('transition', payload: ['instance_id' => $id, 'state' => 'review', 'reason' => 'x'])->assertForbidden();
        $this->method('moveHere', parameters: [['kind' => 'node', 'id' => 'review', 'path' => [], 'instance' => $id]])->assertForbidden();
        $this->assertSame(['new'], $this->engine()->get($id)->leaves());
    }

    public function test_the_drawer_moves_into_the_selected_state(): void
    {
        $this->actingAsAdmin(permissions: [Permissions::VIEW, Permissions::MANAGE]);
        $id = $this->order();

        $this->method('moveHere', parameters: [['kind' => 'node', 'id' => 'review', 'path' => [], 'instance' => $id]])->assertOk();

        $this->assertSame(['review'], $this->engine()->get($id)->leaves());
    }

    public function test_the_context_is_patched(): void
    {
        $this->actingAsAdmin(permissions: [Permissions::VIEW, Permissions::MANAGE]);
        $id = $this->order();

        $this->method('patchContext', payload: ['instance_id' => $id, 'patch' => '{"amount": 120}', 'reason' => 'Typo'])->assertOk();
        $this->assertSame(120, $this->engine()->get($id)->contextArray()['amount']);

        $this->method('patchContext', payload: ['instance_id' => $id, 'patch' => '{oops', 'reason' => 'Typo'])->assertStatus(422);
        $this->method('patchContext', payload: ['instance_id' => $id, 'patch' => '[{"op": "replace", "path": "/amount", "value": 99}]', 'reason' => 'Typo'])->assertOk();
        $this->assertSame(99, $this->engine()->get($id)->contextArray()['amount']);
        $this->method('patchContext', payload: ['instance_id' => $id, 'patch' => '"text"', 'reason' => 'Typo'])->assertStatus(422);
    }

    public function test_an_unknown_outcome_is_settled_by_an_operator(): void
    {
        $this->actingAsAdmin(permissions: [Permissions::VIEW, Permissions::MANAGE]);
        Charge::$timeout = true;
        $id = $this->order(pay: true);
        $view = $this->engine()->get($id);
        $this->assertCount(1, $view->incidents());
        $effect = $view->effects()[0]->id;

        $this->method('resolveEffect', payload: ['instance_id' => $id, 'effect' => $effect, 'outcome' => 'succeeded', 'result' => '"ch_9"', 'reason' => 'Found in the bank statement'])
            ->assertOk();

        $view = $this->engine()->get($id);
        $this->assertSame('completed', $view->status());
        $this->assertSame('paid', $view->final());
        $this->assertSame('ch_9', $view->contextArray()['charge']);
    }

    public function test_an_unsafe_retry_needs_the_manage_permission(): void
    {
        $this->actingAsAdmin(permissions: [Permissions::VIEW, Permissions::OPERATE]);
        Charge::$timeout = true;
        $id = $this->order(pay: true);
        $effect = $this->engine()->get($id)->effects()[0]->id;

        $this->method('retryEffect', payload: ['instance_id' => $id, 'effect' => $effect, 'confirm_unsafe' => true, 'reason' => 'x'])
            ->assertStatus(422)->assertJsonPath('payload.message', 'Retrying an effect with an unknown outcome needs the intervention permission');
    }

    public function test_a_refusal_of_the_engine_is_reported(): void
    {
        $this->actingAsAdmin(permissions: [Permissions::VIEW, Permissions::OPERATE]);
        Charge::$timeout = true;
        $id = $this->order(pay: true);
        $effect = $this->engine()->get($id)->effects()[0]->id;

        // Without confirmUnsafe the engine refuses to run an at-most-once effect twice.
        $response = $this->method('retryEffect', payload: ['instance_id' => $id, 'effect' => $effect, 'reason' => 'Try again'])->assertStatus(422);

        $this->assertStringContainsString('confirmUnsafe', (string) $response->json('payload.message'));
    }

    public function test_an_operator_suspends_from_the_page(): void
    {
        $this->actingAsAdmin(permissions: [Permissions::VIEW, Permissions::OPERATE]);
        $id = $this->order();

        $this->method('suspend', payload: ['instance_id' => $id, 'reason' => 'Hold'])->assertOk()->assertJsonPath('payload.refresh', true);

        $this->assertSame('suspended', $this->engine()->get($id)->status());
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @param  list<mixed>|null  $parameters
     */
    private function method(string $method, ?array $payload = null, ?array $parameters = null): TestResponse
    {
        $body = ['method' => $method];
        if ($payload !== null) {
            $body['payload'] = $payload;
        }
        if ($parameters !== null) {
            $body['parameters'] = $parameters;
        }

        return $this->postJson('/api/admin/process-instance/runMethod', $body);
    }
}
