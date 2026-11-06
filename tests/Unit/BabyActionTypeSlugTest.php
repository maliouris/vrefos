<?php

namespace Tests\Unit;

use App\Models\BabyActionType;
use PHPUnit\Framework\TestCase;

class BabyActionTypeSlugTest extends TestCase
{
    public function test_known_types_map_to_their_slug_case_insensitively(): void
    {
        $this->assertSame('eat', BabyActionType::slugFor('Eat'));
        $this->assertSame('sleep', BabyActionType::slugFor('SLEEP'));
        $this->assertSame('temperature', BabyActionType::slugFor('temperature'));
        $this->assertSame('medication', BabyActionType::slugFor('Medication'));
    }

    public function test_unknown_or_missing_types_fall_back_to_other(): void
    {
        $this->assertSame('other', BabyActionType::slugFor('Bath'));
        $this->assertSame('other', BabyActionType::slugFor(null));
    }
}
