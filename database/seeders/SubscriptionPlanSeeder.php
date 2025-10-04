<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SubscriptionPlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Creates 3 default subscription plans with Luganda and Swahili translations
     */
    public function run(): void
    {
        // Check if plans already exist
        if (SubscriptionPlan::count() > 0) {
            $this->command->info('Subscription plans already exist. Skipping seeder.');
            return;
        }

        $plans = [
            [
                // 3-DAY PLAN
                'name' => 'Quick Start',
                'name_luganda' => 'Ssente Ntono',
                'name_swahili' => 'Bei Ndogo',
                'slug' => 'quick-start-3-days',
                'description' => 'Perfect for testing out the platform. Get full access for 3 days.',
                'description_luganda' => 'Kikugwanira okugezaako. Ofuna okusobola okukozesa pulatifomu okumala ennaku 3.',
                'description_swahili' => 'Bora kwa kujaribu jukwaa. Pata ufikiaji kamili kwa siku 3.',
                'price' => 1000.00,
                'currency' => 'UGX',
                'duration_days' => 3,
                'features' => '
                    <ul>
                        <li>Watch unlimited movies</li>
                        <li>HD streaming quality</li>
                        <li>Ad-free experience</li>
                        <li>Access to all content</li>
                        <li>3 days full access</li>
                    </ul>
                ',
                'features_luganda' => '
                    <ul>
                        <li>Laba firimu ezitali za makalu</li>
                        <li>Obulungi bw\'omutindo gwa HD</li>
                        <li>Tolaba langi za byokulunda</li>
                        <li>Tuuka ku bintu byonna</li>
                        <li>Ennaku 3 z\'okukozesa byonna</li>
                    </ul>
                ',
                'features_swahili' => '
                    <ul>
                        <li>Angalia sinema bila kikomo</li>
                        <li>Ubora wa HD</li>
                        <li>Bila matangazo</li>
                        <li>Ufikiaji wa yote</li>
                        <li>Ufikiaji kamili kwa siku 3</li>
                    </ul>
                ',
                'status' => 'Active',
                'is_featured' => false,
                'sort_order' => 1,
                'discount_percentage' => 0,
                'is_trial' => true,
                'max_downloads' => 5,
                'max_watchlist' => 10,
                'ad_free' => true,
                'hd_streaming' => true,
            ],
            [
                // 14-DAY PLAN
                'name' => 'Two Weeks Special',
                'name_luganda' => 'Wiiki Bbiri',
                'name_swahili' => 'Wiki Mbili',
                'slug' => 'two-weeks-special',
                'description' => 'Great value! Enjoy 2 weeks of unlimited entertainment.',
                'description_luganda' => 'Omuwendo omulungi! Nyumirwa wiiki bbiri z\'okusanyusa okutali kwa makalu.',
                'description_swahili' => 'Thamani nzuri! Furahia wiki 2 za burudani bila kikomo.',
                'price' => 5000.00,
                'currency' => 'UGX',
                'duration_days' => 14,
                'features' => '
                    <ul>
                        <li>Watch unlimited movies</li>
                        <li>Full HD streaming</li>
                        <li>Ad-free experience</li>
                        <li>Download up to 20 movies</li>
                        <li>Add 50 items to watchlist</li>
                        <li>14 days full access</li>
                        <li>Priority support</li>
                    </ul>
                ',
                'features_luganda' => '
                    <ul>
                        <li>Laba firimu ezitali za makalu</li>
                        <li>Obulungi bw\'omutindo gwa HD ow\'amaanyi</li>
                        <li>Tolaba langi za byokulunda</li>
                        <li>Wanula firimu 20</li>
                        <li>Yongerako ebintu 50 ku lukalala lwo</li>
                        <li>Ennaku 14 z\'okukozesa byonna</li>
                        <li>Obuyambi obw\'amaanyi</li>
                    </ul>
                ',
                'features_swahili' => '
                    <ul>
                        <li>Angalia sinema bila kikomo</li>
                        <li>Ubora kamili wa HD</li>
                        <li>Bila matangazo</li>
                        <li>Pakua sinema hadi 20</li>
                        <li>Ongeza vitu 50 kwenye orodha yako</li>
                        <li>Ufikiaji kamili kwa siku 14</li>
                        <li>Msaada wa kipaumbele</li>
                    </ul>
                ',
                'status' => 'Active',
                'is_featured' => false,
                'sort_order' => 2,
                'discount_percentage' => 0,
                'is_trial' => false,
                'max_downloads' => 20,
                'max_watchlist' => 50,
                'ad_free' => true,
                'hd_streaming' => true,
            ],
            [
                // 30-DAY PLAN (MOST POPULAR)
                'name' => 'Monthly Premium',
                'name_luganda' => 'Omwezi Omulungi',
                'name_swahili' => 'Mwezi Mzuri',
                'slug' => 'monthly-premium',
                'description' => 'Best value! Full month of unlimited movies and premium features.',
                'description_luganda' => 'Omuwendo ogusinga obulungi! Omwezi omulamba gw\'ebifaananyi n\'ebikulu byonna.',
                'description_swahili' => 'Thamani bora zaidi! Mwezi mzima wa sinema bila kikomo na vipengele vya premium.',
                'price' => 8000.00,
                'currency' => 'UGX',
                'duration_days' => 30,
                'features' => '
                    <ul>
                        <li>Watch unlimited movies</li>
                        <li>4K Ultra HD streaming</li>
                        <li>Completely ad-free</li>
                        <li>Unlimited downloads</li>
                        <li>Unlimited watchlist</li>
                        <li>30 days full access</li>
                        <li>Priority support 24/7</li>
                        <li>Early access to new releases</li>
                        <li>Exclusive content</li>
                    </ul>
                ',
                'features_luganda' => '
                    <ul>
                        <li>Laba firimu ezitali za makalu</li>
                        <li>Omutindo gw\'e 4K Ultra HD</li>
                        <li>Tolaba langi za byokulunda ddala</li>
                        <li>Wanula firimu ezitali za makalu</li>
                        <li>Lukalala lw\'ebintu oby\'oyagala eritali lya makalu</li>
                        <li>Ennaku 30 z\'okukozesa byonna</li>
                        <li>Obuyambi obw\'amaanyi buli ssaawa</li>
                        <li>Okusookera okulaba ebipya</li>
                        <li>Ebintu by\'enjawulo</li>
                    </ul>
                ',
                'features_swahili' => '
                    <ul>
                        <li>Angalia sinema bila kikomo</li>
                        <li>Ubora wa 4K Ultra HD</li>
                        <li>Bila matangazo kabisa</li>
                        <li>Pakua bila kikomo</li>
                        <li>Orodha bila kikomo</li>
                        <li>Ufikiaji kamili kwa siku 30</li>
                        <li>Msaada wa kipaumbele 24/7</li>
                        <li>Ufikiaji wa mapema wa toleo jipya</li>
                        <li>Maudhui maalum</li>
                    </ul>
                ',
                'status' => 'Active',
                'is_featured' => true, // Mark as featured/recommended
                'sort_order' => 3,
                'discount_percentage' => 0,
                'is_trial' => false,
                'max_downloads' => null, // NULL = unlimited
                'max_watchlist' => null, // NULL = unlimited
                'ad_free' => true,
                'hd_streaming' => true,
            ],
        ];

        foreach ($plans as $planData) {
            SubscriptionPlan::create($planData);
            $this->command->info("Created plan: {$planData['name']} ({$planData['name_luganda']})");
        }

        $this->command->info('✅ Successfully created 3 subscription plans!');
    }
}
