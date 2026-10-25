<?php

// Stand-in for the function provided by the ddtrace PHP extension.
// Defining it makes it visible to the whole PHP process for good, so only
// require this file from tests that run in a separate process.

namespace DDTrace;

function current_context(): array
{
    return ['trace_id' => 'dd-trace-1', 'span_id' => 'dd-span-1'];
}
