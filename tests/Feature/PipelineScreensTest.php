<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminProcess\Tests\Feature;

use Dskripchenko\LaravelAdminProcess\Permissions;
use Dskripchenko\LaravelAdminProcess\Screens\ProcessDefinitionScreen;
use Dskripchenko\LaravelAdminProcess\Tests\Fixtures\Lookup;
use Dskripchenko\LaravelAdminProcess\Tests\Fixtures\Pipeline;
use Dskripchenko\LaravelAdminProcess\Tests\TestCase;
use Dskripchenko\ProcessEngine\Store\BatchRecord;

/**
 * The pages of data pipelines: the list of processes, the diagram of a
 * process with its switches and the start form, batches on the instance page.
 */
final class PipelineScreensTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Lookup::$hold = false;
        config()->set('admin-process.startable', [Pipeline::class => ['n' => 3]]);
    }

    public function test_the_list_of_processes_shows_the_latest_versions_with_counts(): void
    {
        $this->actingAsAdmin(permissions: [Permissions::VIEW]);
        $this->engine()->start(Pipeline::class, ['n' => 5]);
        $this->order();

        $rows = $this->postJson('/api/admin/process-definitions/search', [])->assertOk()->json('payload.data');
        $byName = array_column($rows, null, 'name');

        $this->assertSame(['order-flow', 'pipeline'], array_keys($byName));
        $this->assertSame(1, (int) $byName['pipeline']['running']);
        $this->assertArrayNotHasKey('schema', $byName['pipeline']);
    }

    public function test_the_diagram_shows_the_switches_and_the_counts(): void
    {
        $this->actingAsAdmin(permissions: [Permissions::VIEW]);
        $this->engine()->start(Pipeline::class, ['n' => 5]);

        $state = $this->getJson('/api/admin/process-definition/state?name=pipeline')->assertOk()->json('payload.state');
        $node = array_values(array_filter($state['graph']['nodes'], fn ($n) => $n['id'] === 'lookup'))[0];
        $this->assertTrue($node['enabled']);
        $this->assertSame([['label' => 'Lookup', 'switch' => 'pipeline.lookup', 'on' => 'on', 'reason' => null, 'by' => null, 'at' => null]], $state['switches']);
        $this->assertTrue($state['summary']['startable']);

        $overview = (new ProcessDefinitionScreen)->overview(['instance' => 'pipeline']);
        $this->assertSame(1, ((array) $overview['nodes'])['merge']['count']);
    }

    public function test_a_switch_on_the_diagram_turns_the_stage_off_for_everyone(): void
    {
        $this->actingAsAdmin(permissions: [Permissions::VIEW, Permissions::SWITCHES]);
        $this->engine()->start(Pipeline::class, ['n' => 1]);
        $this->postJson('/api/admin/process-definition/runMethod?name=pipeline', [
            'method' => 'toggleSwitch',
            'parameters' => [['kind' => 'node', 'id' => 'lookup', 'enabled' => false, 'path' => []]],
        ])->assertOk();

        $this->assertFalse($this->engine()->switchState('pipeline.lookup')['on']);
        $id = $this->engine()->start(Pipeline::class, ['n' => 5])->id();
        $this->assertSame(['merge'], $this->engine()->get($id)->active());
        $this->assertSame([], $this->engine()->batches($id));
    }

    public function test_switches_and_start_need_their_permissions(): void
    {
        $this->actingAsAdmin(permissions: [Permissions::VIEW]);
        $this->engine()->start(Pipeline::class, ['n' => 1]);
        $this->postJson('/api/admin/process-definition/runMethod?name=pipeline', [
            'method' => 'toggleSwitch',
            'parameters' => [['kind' => 'node', 'id' => 'lookup', 'enabled' => false]],
        ])->assertForbidden();
        $this->postJson('/api/admin/process-definition/runMethod?name=pipeline', [
            'method' => 'start',
            'payload' => ['definition_name' => 'pipeline', 'context' => '{"n": 1}'],
        ])->assertForbidden();

        $this->assertTrue($this->engine()->switchState('pipeline.lookup')['on']);
    }

    public function test_the_start_form_starts_with_stages_off_for_this_run(): void
    {
        $this->actingAsAdmin(permissions: [Permissions::VIEW, Permissions::START]);
        $this->engine()->start(Pipeline::class, ['n' => 1]);

        $response = $this->postJson('/api/admin/process-definition/runMethod?name=pipeline', [
            'method' => 'start',
            'payload' => ['definition_name' => 'pipeline', 'context' => '{"n": 4}', 'disabled' => ['pipeline.lookup']],
        ])->assertOk();

        $url = (string) $response->json('payload.redirect_url');
        $id = substr($url, strrpos($url, '=') + 1);
        $view = $this->engine()->get($id);
        $this->assertSame(['merge'], $view->active());
        $this->assertSame(['n' => 4, 'disabled' => ['pipeline.lookup'], 'skipped' => [['state' => 'lookup', 'switch' => 'pipeline.lookup', 'reason' => 'off for this run']]], $view->contextArray());
        $this->assertSame('operator', $view->journal()[0]->actor['type'] ?? null);
    }

    public function test_the_instance_page_shows_batches_and_retries_their_elements(): void
    {
        Lookup::$hold = true;
        $this->actingAsAdmin(permissions: [Permissions::VIEW, Permissions::OPERATE]);
        $id = $this->engine()->start(Pipeline::class, ['n' => 10])->id();

        $state = $this->getJson('/api/admin/process-instance/state?id='.$id)->assertOk()->json('payload.state');
        $this->assertSame('running', $state['batches'][0]['status']);
        $this->assertStringStartsWith('8 / 10', $state['batches'][0]['progress']);
        $this->assertSame([['state' => 'lookup', 'key' => '2', 'status' => 'failed', 'attempts' => 1, 'reason' => 'timeout']], $state['batch_items']);

        $batch = $this->engine()->batches($id)[0]->id;
        $this->postJson('/api/admin/process-instance/runMethod', [
            'method' => 'skipBatch',
            'payload' => ['instance_id' => $id, 'batch' => $batch, 'reason' => 'not today'],
        ])->assertOk();

        $this->assertSame(BatchRecord::DONE, $this->engine()->batch($batch)?->status);
        $this->assertSame(['merge'], $this->engine()->get($id)->active());
    }
}
