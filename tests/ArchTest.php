<?php

arch('it will not use debugging functions')
    ->expect(['var_dump', 'dd', 'dump', 'ray'])
    ->each->not->toBeUsed();
