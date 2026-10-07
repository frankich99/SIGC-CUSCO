<?php

namespace App\Providers;

use App\Contracts\DniLookupService;
use App\Services\Dni\PeruDevsDniService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(DniLookupService::class, PeruDevsDniService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureSpanishMailNotifications();
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

    /**
     * Configure Spanish mail notifications.
     */
    protected function configureSpanishMailNotifications(): void
    {
        VerifyEmail::toMailUsing(function (object $notifiable, string $url): MailMessage {
            return (new MailMessage)
                ->subject('Verifica tu correo electrónico - '.config('app.name'))
                ->greeting('¡Hola, '.($notifiable->name ?? 'Usuario').'!')
                ->line('Gracias por registrarte en el Sistema Integral de Gestión de Capacitaciones (SIGC-CUSCO).')
                ->line('Haz clic en el siguiente botón para confirmar tu dirección de correo:')
                ->action('Verificar Correo Electrónico', $url)
                ->line('Si no creaste una cuenta en SIGC-CUSCO, puedes ignorar este mensaje de forma segura.')
                ->salutation("Saludos cordiales,\n".config('app.name'));
        });

        ResetPassword::toMailUsing(function (object $notifiable, string $token): MailMessage {
            $url = url(route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ], false));

            return (new MailMessage)
                ->subject('Restablecer contraseña - '.config('app.name'))
                ->greeting('¡Hola!')
                ->line('Recibiste este correo porque se solicitó restablecer la contraseña de tu cuenta en SIGC-CUSCO.')
                ->action('Restablecer Contraseña', $url)
                ->line('Este enlace de restablecimiento expirará en '.config('auth.passwords.'.config('auth.defaults.passwords').'.expire').' minutos.')
                ->line('Si no solicitaste este cambio, puedes ignorar este mensaje.')
                ->salutation("Saludos cordiales,\n".config('app.name'));
        });
    }
}
