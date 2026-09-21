<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedDefaultCategories();
        $this->seedSystemTemplates();
    }

    private function seedDefaultCategories(): void
    {
        $categories = [
            ['name' => 'Housing & Utilities', 'icon' => 'Home', 'color' => 'emerald', 'sort_order' => 1, 'subcategories' => [
                ['name' => 'Rent / Mortgage', 'icon' => 'Home', 'color' => 'emerald', 'sort_order' => 1],
                ['name' => 'Electricity & Water', 'icon' => 'Zap', 'color' => 'emerald', 'sort_order' => 2],
                ['name' => 'Internet & Mobile', 'icon' => 'Smartphone', 'color' => 'emerald', 'sort_order' => 3],
            ]],
            ['name' => 'Food & Dining', 'icon' => 'Utensils', 'color' => 'amber', 'sort_order' => 2, 'subcategories' => [
                ['name' => 'Supermarket Groceries', 'icon' => 'ShoppingCart', 'color' => 'amber', 'sort_order' => 1],
                ['name' => 'Restaurants & Cafes', 'icon' => 'UtensilsCrossed', 'color' => 'amber', 'sort_order' => 2],
            ]],
            ['name' => 'Transportation', 'icon' => 'Car', 'color' => 'blue', 'sort_order' => 3, 'subcategories' => [
                ['name' => 'Car Fuel / Petrol', 'icon' => 'Fuel', 'color' => 'blue', 'sort_order' => 1],
                ['name' => 'Car Maintenance & Wash', 'icon' => 'Wrench', 'color' => 'blue', 'sort_order' => 2],
                ['name' => 'Public Transit / Taxi', 'icon' => 'Bus', 'color' => 'blue', 'sort_order' => 3],
            ]],
            ['name' => 'Lifestyle & Fun', 'icon' => 'Film', 'color' => 'purple', 'sort_order' => 4, 'subcategories' => [
                ['name' => 'Events & Movies', 'icon' => 'Ticket', 'color' => 'purple', 'sort_order' => 1],
                ['name' => 'Streaming & Apps', 'icon' => 'Monitor', 'color' => 'purple', 'sort_order' => 2],
                ['name' => 'Sports & Hobbies', 'icon' => 'Dumbbell', 'color' => 'purple', 'sort_order' => 3],
            ]],
            ['name' => 'Healthcare & Fitness', 'icon' => 'HeartPulse', 'color' => 'rose', 'sort_order' => 5, 'subcategories' => [
                ['name' => 'Gym Membership', 'icon' => 'Dumbbell', 'color' => 'rose', 'sort_order' => 1],
                ['name' => 'Pharmacy & Medical', 'icon' => 'Pill', 'color' => 'rose', 'sort_order' => 2],
            ]],
            ['name' => 'Savings & Investments', 'icon' => 'PiggyBank', 'color' => 'indigo', 'sort_order' => 6, 'subcategories' => [
                ['name' => 'Emergency Vault', 'icon' => 'ShieldCheck', 'color' => 'indigo', 'sort_order' => 1],
                ['name' => 'Stock Portfolio', 'icon' => 'TrendingUp', 'color' => 'indigo', 'sort_order' => 2],
            ]],
        ];

        foreach ($categories as $i => $cat) {
            $parentId = DB::table('categories')->insertGetId([
                'name' => $cat['name'],
                'icon' => $cat['icon'],
                'color' => $cat['color'],
                'type' => 'expense',
                'is_default' => true,
                'is_archived' => false,
                'sort_order' => $cat['sort_order'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($cat['subcategories'] as $sub) {
                DB::table('categories')->insert([
                    'parent_id' => $parentId,
                    'name' => $sub['name'],
                    'icon' => $sub['icon'],
                    'color' => $sub['color'],
                    'type' => 'expense',
                    'is_default' => true,
                    'is_archived' => false,
                    'sort_order' => $sub['sort_order'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    private function seedSystemTemplates(): void
    {
        $templates = [
            [
                'name' => 'Standard Household (50/30/20)',
                'type' => 'default',
                'description' => 'Balanced monthly distribution for essentials, lifestyle, and automated savings.',
                'icon' => 'Scale',
                'is_default' => true,
                'config' => [
                    'categoryAllocations' => [
                        ['categoryName' => 'Housing & Utilities', 'icon' => 'Home', 'color' => 'emerald', 'percentageOfIncome' => 35, 'subcategories' => [
                            ['name' => 'Rent / Mortgage', 'percentageOfCategory' => 75],
                            ['name' => 'Utilities', 'percentageOfCategory' => 25],
                        ]],
                        ['categoryName' => 'Food & Dining', 'icon' => 'Utensils', 'color' => 'amber', 'percentageOfIncome' => 20, 'subcategories' => [
                            ['name' => 'Supermarket Groceries', 'percentageOfCategory' => 70],
                            ['name' => 'Dining Out', 'percentageOfCategory' => 30],
                        ]],
                        ['categoryName' => 'Transportation', 'icon' => 'Car', 'color' => 'blue', 'percentageOfIncome' => 10, 'subcategories' => [
                            ['name' => 'Fuel & Transit', 'percentageOfCategory' => 80],
                            ['name' => 'Maintenance', 'percentageOfCategory' => 20],
                        ]],
                        ['categoryName' => 'Lifestyle & Fun', 'icon' => 'Film', 'color' => 'purple', 'percentageOfIncome' => 15, 'subcategories' => [
                            ['name' => 'Entertainment', 'percentageOfCategory' => 50],
                            ['name' => 'Subscriptions', 'percentageOfCategory' => 50],
                        ]],
                        ['categoryName' => 'Savings & Investments', 'icon' => 'PiggyBank', 'color' => 'indigo', 'percentageOfIncome' => 20, 'subcategories' => [
                            ['name' => 'Emergency Fund', 'percentageOfCategory' => 60],
                            ['name' => 'Investments', 'percentageOfCategory' => 40],
                        ]],
                    ],
                ],
            ],
            [
                'name' => 'Ramadan Special Budget',
                'type' => 'ramadan',
                'description' => 'Customized allocations prioritizing food, charity (Zakat/Sadaqah), family gatherings, and gifts.',
                'icon' => 'MoonStar',
                'config' => [
                    'categoryAllocations' => [
                        ['categoryName' => 'Ramadan Groceries & Iftar', 'icon' => 'UtensilsCrossed', 'color' => 'amber', 'percentageOfIncome' => 30, 'subcategories' => [
                            ['name' => 'Special Food Supplies', 'percentageOfCategory' => 60],
                            ['name' => 'Iftar Gatherings & Catering', 'percentageOfCategory' => 40],
                        ]],
                        ['categoryName' => 'Zakat & Charitable Giving', 'icon' => 'HeartHandshake', 'color' => 'emerald', 'percentageOfIncome' => 20, 'subcategories' => [
                            ['name' => 'Zakat al-Fitr & Alms', 'percentageOfCategory' => 70],
                            ['name' => 'Community Sponsorships', 'percentageOfCategory' => 30],
                        ]],
                        ['categoryName' => 'Eid Gifts & Hospitality', 'icon' => 'Gift', 'color' => 'rose', 'percentageOfIncome' => 15, 'subcategories' => [
                            ['name' => 'Eid Clothing & Gifts', 'percentageOfCategory' => 60],
                            ['name' => 'Hospitality & Sweets', 'percentageOfCategory' => 40],
                        ]],
                        ['categoryName' => 'Housing & Essential Utilities', 'icon' => 'Home', 'color' => 'blue', 'percentageOfIncome' => 25, 'subcategories' => [
                            ['name' => 'Rent & Housing', 'percentageOfCategory' => 80],
                            ['name' => 'Utilities', 'percentageOfCategory' => 20],
                        ]],
                        ['categoryName' => 'Emergency Savings', 'icon' => 'PiggyBank', 'color' => 'indigo', 'percentageOfIncome' => 10, 'subcategories' => [
                            ['name' => 'Monthly Buffer', 'percentageOfCategory' => 100],
                        ]],
                    ],
                ],
            ],
            [
                'name' => 'Vacation & Travel Budget',
                'type' => 'travel',
                'description' => 'Designed for holiday trips, flights, lodging, excursions, and souvenirs.',
                'icon' => 'Plane',
                'config' => [
                    'categoryAllocations' => [
                        ['categoryName' => 'Flights & Transit', 'icon' => 'PlaneTakeoff', 'color' => 'blue', 'percentageOfIncome' => 30, 'subcategories' => [
                            ['name' => 'Airline Tickets', 'percentageOfCategory' => 75],
                            ['name' => 'Local Taxis & Car Rental', 'percentageOfCategory' => 25],
                        ]],
                        ['categoryName' => 'Hotels & Accommodation', 'icon' => 'Hotel', 'color' => 'indigo', 'percentageOfIncome' => 30, 'subcategories' => [
                            ['name' => 'Resort & Hotel Stays', 'percentageOfCategory' => 100],
                        ]],
                        ['categoryName' => 'Dining & Food Experience', 'icon' => 'Utensils', 'color' => 'amber', 'percentageOfIncome' => 20, 'subcategories' => [
                            ['name' => 'Local Restaurants', 'percentageOfCategory' => 70],
                            ['name' => 'Snacks & Cafes', 'percentageOfCategory' => 30],
                        ]],
                        ['categoryName' => 'Excursions & Shopping', 'icon' => 'ShoppingBag', 'color' => 'purple', 'percentageOfIncome' => 20, 'subcategories' => [
                            ['name' => 'Guided Tours & Tickets', 'percentageOfCategory' => 50],
                            ['name' => 'Souvenirs & Gifts', 'percentageOfCategory' => 50],
                        ]],
                    ],
                ],
            ],
            [
                'name' => 'Back to School Season',
                'type' => 'school',
                'description' => 'Focused allocation for tuition fees, textbooks, uniforms, supplies, and electronics.',
                'icon' => 'GraduationCap',
                'config' => [
                    'categoryAllocations' => [
                        ['categoryName' => 'Tuition & Academic Fees', 'icon' => 'BookOpen', 'color' => 'indigo', 'percentageOfIncome' => 50, 'subcategories' => [
                            ['name' => 'School/College Fees', 'percentageOfCategory' => 80],
                            ['name' => 'Extracurricular Activities', 'percentageOfCategory' => 20],
                        ]],
                        ['categoryName' => 'Uniforms & Clothing', 'icon' => 'Shirt', 'color' => 'emerald', 'percentageOfIncome' => 15, 'subcategories' => [
                            ['name' => 'School Uniforms', 'percentageOfCategory' => 70],
                            ['name' => 'Sports Gear', 'percentageOfCategory' => 30],
                        ]],
                        ['categoryName' => 'Supplies & Devices', 'icon' => 'Laptop', 'color' => 'blue', 'percentageOfIncome' => 20, 'subcategories' => [
                            ['name' => 'Laptops/Tablets', 'percentageOfCategory' => 60],
                            ['name' => 'Stationery & Books', 'percentageOfCategory' => 40],
                        ]],
                        ['categoryName' => 'General Household', 'icon' => 'Home', 'color' => 'amber', 'percentageOfIncome' => 15, 'subcategories' => [
                            ['name' => 'Daily Living Essentials', 'percentageOfCategory' => 100],
                        ]],
                    ],
                ],
            ],
        ];

        foreach ($templates as $tpl) {
            DB::table('templates')->insert([
                'name' => $tpl['name'],
                'type' => $tpl['type'],
                'description' => $tpl['description'],
                'icon' => $tpl['icon'],
                'is_system' => true,
                'is_default' => $tpl['is_default'] ?? false,
                'config' => json_encode($tpl['config']),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
