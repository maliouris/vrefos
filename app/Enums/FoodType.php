<?php

namespace App\Enums;

enum FoodType: string
{
    case BreastMilkLeft = 'breast_milk_left';
    case BreastMilkRight = 'breast_milk_right';
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
            self::BreastMilkLeft => 'Breast Milk (Left)',
            self::BreastMilkRight => 'Breast Milk (Right)',
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
