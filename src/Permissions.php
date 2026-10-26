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
 * effect whose outcome is unknown).
 */
final class Permissions
{
    public const INSTANCES = 'admin.process.instances';

    public const INCIDENTS = 'admin.process.incidents';

    public const VIEW = 'admin.process.instances.view';

    public const INCIDENTS_VIEW = 'admin.process.incidents.view';

    public const OPERATE = 'admin.process.operate';

    public const MANAGE = 'admin.process.manage';
}
