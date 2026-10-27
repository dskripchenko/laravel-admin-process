<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminProcess;

/**
 * The permission keys of the pack.
 *
 * `view` opens the lists and the instance page; `operate` covers the routine
 * operator actions (suspend, resume, cancel, retry an effect, resolve an
 * incident); `manage` covers the actions that bypass the process logic
 * (move to a state, edit the context, settle or skip an effect, retry an
 * effect whose outcome is unknown); `switches` turns stages of a pipeline
 * on and off; `start` starts a process by hand.
 */
final class Permissions
{
    public const INSTANCES = 'admin.process.instances';

    public const INCIDENTS = 'admin.process.incidents';

    public const VIEW = 'admin.process.instances.view';

    public const INCIDENTS_VIEW = 'admin.process.incidents.view';

    public const OPERATE = 'admin.process.operate';

    public const MANAGE = 'admin.process.manage';

    /** Switch stages of switchable states on and off (ADR-015 of the engine). */
    public const SWITCHES = 'admin.process.switches';

    /** Start a process by hand from its diagram. */
    public const START = 'admin.process.start';
}
