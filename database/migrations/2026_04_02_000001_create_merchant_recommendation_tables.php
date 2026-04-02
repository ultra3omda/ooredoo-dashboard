<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Aggregated user-merchant interaction features (pre-computed for ML)
        if (!Schema::hasTable('cp_user_merchant_history')) {
            Schema::create('cp_user_merchant_history', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('client_id');
                $table->unsignedBigInteger('partner_id');
                $table->integer('visit_count')->default(0);
                $table->integer('unique_promotions_used')->default(0);
                $table->datetime('first_visit')->nullable();
                $table->datetime('last_visit')->nullable();
                $table->integer('days_since_last_visit')->default(0);
                $table->decimal('avg_days_between_visits', 8, 2)->default(0);
                $table->decimal('recency_score', 5, 4)->default(0);
                $table->decimal('frequency_score', 5, 4)->default(0);
                $table->timestamps();

                $table->unique(['client_id', 'partner_id'], 'umh_client_partner_unique');
                $table->index('client_id', 'umh_client_idx');
                $table->index('partner_id', 'umh_partner_idx');
                $table->index('visit_count', 'umh_visit_count_idx');
            });
        }

        // 2. Enriched merchant catalog for recommendation scoring
        if (!Schema::hasTable('cp_merchants_catalog')) {
            Schema::create('cp_merchants_catalog', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('partner_id')->unique();
                $table->string('partner_name');
                $table->unsignedBigInteger('category_id')->nullable();
                $table->string('category_name')->nullable();
                $table->integer('location_count')->default(0);
                $table->integer('active_promotion_count')->default(0);
                $table->integer('total_promotion_count')->default(0);
                $table->decimal('avg_discount', 5, 2)->default(0);
                $table->decimal('max_discount', 5, 2)->default(0);
                $table->integer('total_visits')->default(0);
                $table->integer('unique_visitors')->default(0);
                $table->decimal('popularity_score', 8, 4)->default(0);
                $table->decimal('avg_visits_per_user', 8, 4)->default(0);
                $table->boolean('is_active')->default(true);
                $table->boolean('is_featured')->default(false);
                $table->boolean('is_premium')->default(false);
                $table->timestamps();

                $table->index('category_id', 'mc_category_idx');
                $table->index('popularity_score', 'mc_popularity_idx');
                $table->index('is_active', 'mc_active_idx');
            });
        }

        // 3. User profile features for ML recommendation
        if (!Schema::hasTable('cp_user_profile')) {
            Schema::create('cp_user_profile', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('client_id')->unique();
                $table->integer('total_visits')->default(0);
                $table->integer('unique_merchants_visited')->default(0);
                $table->integer('unique_categories_visited')->default(0);
                $table->unsignedBigInteger('favorite_category_id')->nullable();
                $table->string('favorite_category_name')->nullable();
                $table->unsignedBigInteger('favorite_merchant_id')->nullable();
                $table->integer('days_since_last_activity')->default(0);
                $table->decimal('avg_visits_per_merchant', 8, 4)->default(0);
                $table->decimal('category_diversity_score', 5, 4)->default(0);
                $table->decimal('loyalty_score', 5, 4)->default(0);
                $table->string('subscription_type')->nullable();
                $table->string('gender')->nullable();
                $table->integer('age')->nullable();
                $table->unsignedBigInteger('sub_store_id')->nullable();
                $table->timestamps();

                $table->index('favorite_category_id', 'up_fav_cat_idx');
                $table->index('total_visits', 'up_visits_idx');
                $table->index('sub_store_id', 'up_sub_store_idx');
            });
        }

        // 4. Interaction tracking for feedback loop
        if (!Schema::hasTable('cp_user_offer_interactions')) {
            Schema::create('cp_user_offer_interactions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('client_id');
                $table->unsignedBigInteger('partner_id');
                $table->unsignedBigInteger('promotion_id')->nullable();
                $table->enum('interaction_type', ['impression', 'click', 'redeem', 'dismiss', 'share']);
                $table->enum('source', ['recommendation', 'organic', 'search', 'category_browse']);
                $table->unsignedBigInteger('recommendation_id')->nullable();
                $table->decimal('recommendation_score', 8, 6)->nullable();
                $table->integer('recommendation_rank')->nullable();
                $table->timestamps();

                $table->index(['client_id', 'created_at'], 'uoi_client_time_idx');
                $table->index(['partner_id', 'created_at'], 'uoi_partner_time_idx');
                $table->index(['interaction_type', 'source'], 'uoi_type_source_idx');
                $table->index('recommendation_id', 'uoi_reco_idx');
            });
        }

        // 5. Extend ml_recommendations enum to include merchant_recommendation
        try {
            DB::statement("ALTER TABLE ml_recommendations MODIFY COLUMN recommendation_type ENUM('pricing','timing','frequency','segmentation','retry_strategy','churn_prevention','global_strategy','merchant_recommendation')");
        } catch (\Exception $e) {
            // Enum already includes the value or table doesn't exist
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cp_user_offer_interactions');
        Schema::dropIfExists('cp_user_profile');
        Schema::dropIfExists('cp_merchants_catalog');
        Schema::dropIfExists('cp_user_merchant_history');

        try {
            DB::statement("ALTER TABLE ml_recommendations MODIFY COLUMN recommendation_type ENUM('pricing','timing','frequency','segmentation','retry_strategy','churn_prevention','global_strategy')");
        } catch (\Exception $e) {
            // ignore
        }
    }
};
