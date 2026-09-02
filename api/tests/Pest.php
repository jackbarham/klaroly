<?php

use Tests\TestCase;

// Feature tests boot the application. Unit tests do not.
pest()->extend(TestCase::class)->in('Feature');
