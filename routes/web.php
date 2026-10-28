<?php

use App\Http\Controllers\ApplicationDocumentController;
use App\Http\Controllers\Career\JobApplicationController;
use App\Http\Controllers\Career\MyApplicationsController;
use App\Http\Controllers\Career\VacancyDetailController;
use App\Http\Controllers\Career\VacancyListController;
use App\Http\Controllers\Course\AssignmentDownloadController;
use App\Http\Controllers\Course\AssignmentSubmissionController;
use App\Http\Controllers\Course\CourseCatalogController;
use App\Http\Controllers\Course\CourseCheckoutController;
use App\Http\Controllers\Course\CourseDetailController;
use App\Http\Controllers\Course\CoursePlayerController;
use App\Http\Controllers\Course\LessonCompletionController;
use App\Http\Controllers\Customer\AccountController;
use App\Http\Controllers\Customer\Auth\EmailVerificationController;
use App\Http\Controllers\Customer\Auth\LoginController;
use App\Http\Controllers\Customer\Auth\LogoutController;
use App\Http\Controllers\Customer\Auth\PasswordResetController;
use App\Http\Controllers\Customer\Auth\RegisterController;
use App\Http\Controllers\Customer\MyCoursesController;
use App\Http\Controllers\Event\EnrollmentController;
use App\Http\Controllers\Event\EventDetailController;
use App\Http\Controllers\Event\EventListController;
use App\Http\Controllers\Event\MyEventsController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\Payment\MidtransNotificationController;
use App\Http\Controllers\Payment\PaymentController;
use App\Http\Controllers\Payment\PaymentProofController;
use App\Http\Controllers\PortfolioController;
use App\Http\Controllers\Project\ProjectLookupController;
use App\Http\Controllers\ServiceController;
use App\Http\Middleware\EnsureCustomerIsActive;
use Illuminate\Support\Facades\Route;

/*
|------------------------------------------------------------------------------
| Public routes
|------------------------------------------------------------------------------
| Legacy routing was the filesystem: index.php, index-en.php, karir.php,
| detail.php?id=5. That cost the site clean URLs, a canonical structure, and
| keyword slugs -- on a company profile site whose whole job is being found.
|
| Locale handling: the default locale (id) is served WITHOUT a prefix, so "/"
| keeps meaning the Indonesian homepage and the currently-indexed URL does not
| change. English is served under /en.
|
| Every old URL is 301'd by HandleLegacyRedirects, which runs on 404s and reads
| the legacy_redirects table populated during data migration.
*/

// Serves user uploads from a private disk. Not locale-prefixed: it returns
// bytes, not language. The pattern excludes '..' to block path traversal.
Route::get('media/{path}', [MediaController::class, 'show'])
    ->where('path', '^(?!.*\.\.)[A-Za-z0-9\-_/\.% ]+$')
    ->name('media.show');

/*
 * Applicant document download (P6). STAFF ONLY.
 *
 * Not locale-prefixed -- it returns bytes, not language -- and deliberately
 * outside the customer route groups: the 'applications' disk sits under
 * storage_path with no public URL, so this is the single path to a CV, and it
 * authorises through JobApplicationPolicy::viewDocuments before streaming.
 */
Route::get('dokumen-lamaran/{document}', ApplicationDocumentController::class)
    ->middleware(['auth', 'can:viewAny,App\Models\JobApplication'])
    ->name('applications.document');

/*
 * One definition, registered once per locale.
 *
 * This was previously a single group prefixed '{locale?}', which looked tidier
 * but could not work: an optional route parameter cannot be followed by a
 * required segment, so '{locale?}/portofolio' made the locale mandatory. "/"
 * was the only route unaffected, being the one with nothing after the
 * parameter -- so the flaw stayed invisible until a second route was added.
 *
 * Explicit groups instead. The locale is passed to the middleware rather than
 * captured as a parameter, which means no URL::defaults plumbing, no optional
 * segments, and a route table that survives route:cache unchanged.
 *
 * Names: the default locale keeps the canonical names ('home'), other locales
 * are namespaced ('en.home'). Use Locale::route() to reach the current
 * locale's variant; see App\Support\Locale.
 */
$publicRoutes = function (): void {
    Route::get('/', HomeController::class)->name('home');

    // Bound by slug (Portfolio::getRouteKeyName), so the URL carries the
    // project name rather than detail.php?id=5.
    Route::get('portofolio', [PortfolioController::class, 'index'])
        ->name('portfolio.index');

    Route::get('portofolio/{portfolio}', [PortfolioController::class, 'show'])
        ->name('portfolio.show');

    // Services & Pricing
    Route::get('layanan', ServiceController::class)->name('services.index');

    // Events (P5a). Browsing is public; enrolling needs a verified customer.
    Route::get('acara', EventListController::class)->name('events.index');
    Route::get('acara/{event}', EventDetailController::class)->name('events.show');

    // Careers (P6). Browsing is public; applying needs a verified customer.
    Route::get('karir', VacancyListController::class)->name('careers.index');
    Route::get('karir/{vacancy}', VacancyDetailController::class)->name('careers.show');

    // Courses (P3/P4). Browsing is public; purchasing & learning is gated.
    Route::get('kursus', CourseCatalogController::class)->name('courses.index');
    Route::get('kursus/{course:slug}', CourseDetailController::class)->name('courses.show');
    Route::get('kursus/{course:slug}/belajar/{lesson?}', [CoursePlayerController::class, 'show'])->name('courses.player.show');

    // Shared payment page (courses + paid events). Owner-only: the page shows
    // the customer's name, order, and a payable QR. The simulation POST exists
    // only for local/testing and is never rendered as a public button.
    Route::middleware(['auth:customer', EnsureCustomerIsActive::class, 'verified.customer'])->group(function () {
        Route::get('pembayaran/{order_id}', [PaymentController::class, 'show'])->name('payments.show');
        Route::post('pembayaran/{order_id}/simulasi', [PaymentController::class, 'simulate'])->name('payments.simulate');

        // Manual bank transfer: the customer uploads a receipt and can
        // re-open the file they sent. Owner-only, checked in the controller.
        Route::post('pembayaran/{order_id}/bukti', [PaymentProofController::class, 'store'])
            ->name('payments.proof.store');
        Route::get('pembayaran/{order_id}/bukti', [PaymentProofController::class, 'file'])
            ->name('payments.proof.file');
    });

    /*
     * Progress tracker (P7). No account required (A6).
     *
     * POST for the lookup, not GET: the submitted phone number or email is
     * personal data and must not land in browser history, server access logs,
     * or a Referer header. Rate limited per code AND per IP -- see the
     * 'project-lookup' limiter.
     */
    Route::get('lacak-proyek', [ProjectLookupController::class, 'show'])
        ->name('tracker.show');

    Route::post('lacak-proyek', [ProjectLookupController::class, 'find'])
        ->middleware('throttle:project-lookup')
        ->name('tracker.find');

    /*
     * Public accounts (P1). The `customer` guard, never `web` -- see D1.
     *
     * Throttles are named limiters defined in AppServiceProvider.
     * customer-register is deliberately not IP-only: CGNAT and shared venue
     * WiFi would otherwise 429 most of a 500-person launch (D17).
     */
    Route::middleware('guest:customer')->group(function () {
        Route::get('daftar', [RegisterController::class, 'create'])->name('customer.register');
        Route::post('daftar', [RegisterController::class, 'store'])
            ->middleware('throttle:customer-register');

        Route::get('masuk', [LoginController::class, 'create'])->name('customer.login');
        Route::post('masuk', [LoginController::class, 'store'])
            ->middleware('throttle:customer-login');

        Route::get('lupa-sandi', [PasswordResetController::class, 'request'])
            ->name('customer.password.request');
        Route::post('lupa-sandi', [PasswordResetController::class, 'email'])
            ->middleware('throttle:customer-password-reset')
            ->name('customer.password.email');

        Route::get('atur-sandi/{token}', [PasswordResetController::class, 'reset'])
            ->name('customer.password.reset');
        Route::post('atur-sandi', [PasswordResetController::class, 'update'])
            ->middleware('throttle:customer-password-reset')
            ->name('customer.password.update');
    });

    // Email verification link (signed) can be opened by both guests and authenticated users.
    Route::get('verifikasi-email/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware('signed')
        ->name('customer.verification.verify');

    Route::middleware(['auth:customer', EnsureCustomerIsActive::class])->group(function () {
        Route::post('keluar', LogoutController::class)->name('customer.logout');

        Route::get('verifikasi-email', [EmailVerificationController::class, 'notice'])
            ->name('customer.verification.notice');

        Route::post('verifikasi-email/kirim-ulang', [EmailVerificationController::class, 'resend'])
            ->middleware('throttle:customer-verification-resend')
            ->name('customer.verification.resend');

        // Verified-only area (A20: this gate is where that decision lands).
        Route::middleware('verified.customer')->group(function () {
            Route::get('akun', AccountController::class)->name('customer.account');

            Route::get('akun/acara', MyEventsController::class)->name('customer.events');

            // POST: enrolling claims a seat, so it must not be reachable by a
            // link, a prefetch, or an <img src> (guide.md 10.4).
            Route::post('acara/{event}/daftar', [EnrollmentController::class, 'store'])
                ->name('events.enroll');

            Route::delete('pendaftaran/{registration}', [EnrollmentController::class, 'destroy'])
                ->name('events.registration.cancel');

            // Careers (P6).
            Route::get('akun/lamaran', MyApplicationsController::class)
                ->name('customer.applications');

            Route::get('karir/{vacancy}/lamar', [JobApplicationController::class, 'create'])
                ->name('careers.apply');

            Route::post('karir/{vacancy}/lamar', [JobApplicationController::class, 'store'])
                ->middleware('throttle:customer-apply')
                ->name('careers.apply.store');

            Route::delete('lamaran/{application}', [JobApplicationController::class, 'withdraw'])
                ->name('careers.withdraw');

            // Courses (P3/P4).
            Route::get('kursus/{course:slug}/checkout', [CourseCheckoutController::class, 'show'])
                ->name('courses.checkout.show');
            Route::post('kursus/{course:slug}/checkout', [CourseCheckoutController::class, 'store'])
                ->middleware('throttle:customer-apply')
                ->name('courses.checkout.store');
            Route::get('akun/kursus', MyCoursesController::class)
                ->name('customer.courses');

            Route::post('kursus/{course:slug}/materi/{lesson}/selesai', [LessonCompletionController::class, 'store'])
                ->name('courses.lesson.complete');

            Route::post('kursus/{course:slug}/tugas/{assignment}/kumpul', [AssignmentSubmissionController::class, 'store'])
                ->name('courses.assignment.submit');

            Route::get('kursus/{course:slug}/tugas/{assignment}/berkas', [AssignmentDownloadController::class, 'download'])
                ->name('courses.assignment.download');
        });
    });
};

$defaultLocale = config('site.default_locale');

// Default locale: no prefix, canonical route names.
Route::middleware('locale:'.$defaultLocale)->group($publicRoutes);

// Every other locale: prefixed path, prefixed route names.
foreach (array_keys(config('site.locales')) as $locale) {
    if ($locale === $defaultLocale) {
        continue;
    }

    Route::prefix($locale)
        ->middleware('locale:'.$locale)
        ->name($locale.'.')
        ->group($publicRoutes);
}

/*
 * Midtrans HTTP notification.
 *
 * Deliberately not locale-prefixed and outside the customer auth groups: this
 * is a server-to-server callback. CSRF is excepted in bootstrap/app.php; the
 * SHA-512 signature check in the controller is the authentication.
 */
Route::post('pembayaran/notifikasi', MidtransNotificationController::class)
    ->name('payments.notification');

Route::middleware('auth')
    ->get('admin/tugas/{submission}/berkas', [AssignmentDownloadController::class, 'downloadStaff'])
    ->name('admin.assignment-submissions.download');

/*
 * Payment proof download for staff (manual bank transfer receipts).
 *
 * Same pattern as the applicant-document route above: outside the locale
 * groups because it returns bytes, protected by 'auth', and the controller
 * re-checks AREA_PAYMENTS before streaming.
 */
Route::middleware('auth')
    ->get('admin/pembayaran/{payment}/bukti', [PaymentProofController::class, 'staff'])
    ->name('admin.payments.proof');
