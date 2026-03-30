<?php

namespace App\Enums;

enum FoodType: string
{
    case BreastMilk = 'breast_milk';
    case Formula = 'formula';
    case Fruits = 'fruits';
    case Vegetables = 'vegetables';
    case Grains = 'grains';
    case Protein = 'protein';
    case Dairy = 'dairy';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::BreastMilk => 'Breast Milk',
            self::Formula => 'Formula',
            self::Fruits => 'Fruits',
            self::Vegetables => 'Vegetables',
            self::Grains => 'Grains',
            self::Protein => 'Protein',
            self::Dairy => 'Dairy',
            self::Other => 'Other',
        };
    }
}
