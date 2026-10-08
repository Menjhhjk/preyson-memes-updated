<?php

namespace App\Providers;

use App\Models\MemberNotification;
use App\Models\SuperReactionType;
use App\Support\PowerCharges;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        View::composer(['layouts.site', 'partials.post-card'], function ($view) {
            $request = request();
            $data = $request->attributes->get('engagement_view_data');
            if ($data === null) {
                $member = $request->user();
                $data = ['unreadNotifications' => $member ? MemberNotification::where('user_id', $member->id)->whereNull('read_at')->count() : 0,
                    'powerCatalog' => $member ? SuperReactionType::where('is_active', true)->orderBy('id')->get() : collect(),
                    'superBalance' => $member ? PowerCharges::balance($member, 'super') : null,
                    'boostBalance' => $member ? PowerCharges::balance($member, 'boost') : null];
                $request->attributes->set('engagement_view_data', $data);
            }
            $view->with($data);
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
