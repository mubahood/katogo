<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class FreeTrialPlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Creates a special "Free Trial" subscription plan with 15 days duration
     * Ensures no duplicates by checking for existing free trial plans
     */
    public function run(): void
    {
        // CRITICAL: Check if free trial plan already exists
        // We check multiple conditions to ensure no duplicates
        $existingFreeTrial = SubscriptionPlan::where(function ($query) {
            $query->where('slug', 'free-trial-15-days')
                  ->orWhere('name', 'Free Trial')
                  ->orWhere(function ($subQuery) {
                      $subQuery->where('price', 0)
                               ->where('duration_days', 15)
                               ->where('is_trial', true);
                  });
        })->first();

        if ($existingFreeTrial) {
            $this->command->info("Free Trial plan already exists (ID: {$existingFreeTrial->id}). Skipping creation.");
           
            return;
        }

        // Create the Free Trial plan
        $freeTrialPlan = [
            'name' => 'Free Trial',
            'name_luganda' => 'Okugezaako Okwa Bwereere',
            'name_swahili' => 'Majaribio ya Bure',
            'slug' => 'free-trial-15-days',
            'description' => 'Get started with a completely free 15-day trial! No payment required. Experience all premium features.',
            'description_luganda' => 'Tandika n\'okugezaako okwa bwereere okw\'ennaku 15! Toliiba na kwasasula. Tandika okumanya ebintu byonna eby\'omuwendo.',
            'description_swahili' => 'Anza na majaribio ya bure ya siku 15! Hakuna malipo yanayohitajika. Hisi vipengele vyote vya bei.',
            'price' => 0.00, // CRITICAL: Free trial is completely free
            'currency' => 'UGX',
            'duration_days' => 15, // CRITICAL: 15 days as requested
            'features' => '
                <ul>
                    <li><strong>✅ Completely FREE</strong> - No payment required</li>
                    <li><strong>✅ 15 Days Full Access</strong> - All premium features</li>
                    <li><strong>✅ Watch Unlimited Movies</strong> - No restrictions</li>
                    <li><strong>✅ HD Streaming Quality</strong> - Crystal clear video</li>
                    <li><strong>✅ Ad-Free Experience</strong> - No interruptions</li>
                    <li><strong>✅ Download Movies</strong> - Up to 10 downloads</li>
                    <li><strong>✅ Watchlist</strong> - Save up to 25 movies</li>
                    <li><strong>✅ All Content Access</strong> - Full library</li>
                    <li><strong>🔄 Auto-Assigned</strong> - Given automatically to new users</li>
                </ul>
            ',
            'features_luganda' => '
                <ul>
                    <li><strong>✅ Bwereere Ddala</strong> - Tokwetaaga kusasula</li>
                    <li><strong>✅ Ennaku 15 z\'Okukozesa Byonna</strong> - Ebintu byonna eby\'omuwendo</li>
                    <li><strong>✅ Laba Firimu Ezitali za Makalu</strong> - Tewali kukugira</li>
                    <li><strong>✅ Omutindo gwa HD</strong> - Obulungi bw\'olutimbe</li>
                    <li><strong>✅ Tolaba Langi za Byokulunda</strong> - Tewali kutabulwa</li>
                    <li><strong>✅ Wanula Firimu</strong> - Okutuusa ku 10</li>
                    <li><strong>✅ Lukalala lw\'Eby\'oyagala</strong> - Tereka firimu 25</li>
                    <li><strong>✅ Tuuka ku Bintu Byonna</strong> - Ekitongole kyonna</li>
                    <li><strong>🔄 Kigabibwa Buli Omu</strong> - Kiweebwa buli mukozesa omupya</li>
                </ul>
            ',
            'features_swahili' => '
                <ul>
                    <li><strong>✅ Kabisa Bure</strong> - Hakuna malipo yanayohitajika</li>
                    <li><strong>✅ Ufikiaji Kamili wa Siku 15</strong> - Vipengele vyote vya bei</li>
                    <li><strong>✅ Angalia Sinema Bila Kikomo</strong> - Hakuna vikwazo</li>
                    <li><strong>✅ Ubora wa HD</strong> - Video wazi kabisa</li>
                    <li><strong>✅ Bila Matangazo</strong> - Hakuna kukatizwa</li>
                    <li><strong>✅ Pakua Sinema</strong> - Hadi downloads 10</li>
                    <li><strong>✅ Orodha ya Kutazama</strong> - Hifadhi sinema 25</li>
                    <li><strong>✅ Ufikiaji wa Maudhui Yote</strong> - Maktaba nzima</li>
                    <li><strong>🔄 Kutolewa Otomatiki</strong> - Kupewa kiotomatiki kwa watumiaji wapya</li>
                </ul>
            ',
            'status' => 'Active',
            'is_featured' => false, // Not featured, but automatically assigned
            'sort_order' => 0, // CRITICAL: Highest priority (sort order 0 = first)
            'discount_percentage' => 100.00, // 100% discount (completely free)
            'is_trial' => true, // CRITICAL: Mark as trial plan
            'max_downloads' => 10, // Allow limited downloads during trial
            'max_watchlist' => 25, // Allow reasonable watchlist size
            'ad_free' => true, // Trial should be ad-free for good experience
            'hd_streaming' => true, // Full HD during trial
            'created_by' => 1, // Assume admin user ID 1
            'updated_by' => 1,
        ];

        try {
            $createdPlan = SubscriptionPlan::create($freeTrialPlan);
            
            $this->command->info("✅ Successfully created Free Trial plan!");
            $this->command->info("   - Plan ID: {$createdPlan->id}");
            $this->command->info("   - Name: {$createdPlan->name}");
            $this->command->info("   - Slug: {$createdPlan->slug}");
            $this->command->info("   - Duration: {$createdPlan->duration_days} days");
            $this->command->info("   - Price: {$createdPlan->currency} {$createdPlan->price}");
            
     
            
        } catch (\Exception $e) {
            $this->command->error("❌ Failed to create Free Trial plan: " . $e->getMessage());
            Log::error('Free Trial Plan Creation Failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'plan_data' => $freeTrialPlan,
            ]);
            throw $e;
        }
    }
}