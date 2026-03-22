<?php

declare(strict_types=1);

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\BlogTopicController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\PortfolioController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\ServiceCityController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SeoMetaController;
use App\Http\Controllers\MetaController;
use App\Http\Controllers\PortfolioServiceTabSeoMetaController;
use App\Http\Controllers\TestimonialController;
use App\Http\Controllers\UploadController;
use App\Http\Middleware\AdminAuthMiddleware;
use Slim\App;

return static function (App $app): void {
    $controller = static function (string $class, string $method) {
        return static function ($request, $response, array $args = []) use ($class, $method) {
            $instance = new $class();
            return $instance->$method($request, $response, $args);
        };
    };

    $adminAuth = new AdminAuthMiddleware();

    $app->get('/api/health', static function ($request, $response) {
        $payload = [
            'status' => 'ok',
            'timestamp' => (new DateTimeImmutable('now'))->format(DateTimeInterface::ATOM),
        ];
        $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json');
    });

    // Public, unauthenticated content APIs (frontend consumption)
    $app->get('/api/public/services', $controller(ServiceController::class, 'index'));
    $app->get('/api/public/services/minimal', $controller(ServiceController::class, 'indexMinimal'));
    $app->get('/api/public/services/{identifier}', $controller(ServiceController::class, 'show'));

    // Service cities (public)
    $app->get('/api/public/services/{identifier}/cities', $controller(ServiceCityController::class, 'publicIndex'));
    $app->get('/api/public/services/{identifier}/cities/{citySlug}', $controller(ServiceCityController::class, 'publicShow'));

    $app->get('/api/public/portfolios', $controller(PortfolioController::class, 'index'));
    $app->get('/api/public/portfolios/home', $controller(PortfolioController::class, 'home'));
    $app->get('/api/public/portfolios/meta/{slug}', $controller(PortfolioController::class, 'showMetaBySlug'));
    $app->get('/api/public/portfolios/slug/{slug}', $controller(PortfolioController::class, 'showBySlug'));
    $app->get('/api/public/portfolios/{id}', $controller(PortfolioController::class, 'show'));

    $app->get('/api/public/blogs', $controller(BlogController::class, 'index'));
    $app->get('/api/public/blogs/slug/{slug}', $controller(BlogController::class, 'showBySlug'));
    $app->get('/api/public/blogs/{id}', $controller(BlogController::class, 'show'));
    $app->get('/api/public/blog-topics', $controller(BlogTopicController::class, 'index'));
    $app->get('/api/public/blog-topics/slug/{slug}', $controller(BlogTopicController::class, 'showBySlug'));

    $app->get('/api/public/seo-meta', $controller(SeoMetaController::class, 'publicIndex'));

    $app->get('/api/public/meta', $controller(MetaController::class, 'show'));// unified meta
    $app->get('/api/public/testimonials', $controller(TestimonialController::class, 'index'));

    $app->post('/api/public/contact-queries', $controller(ContactController::class, 'store'));
    $app->post('/api/public/quote-requests', $controller(QuoteController::class, 'store'));

    // Settings & authentication
    $app->get('/api/settings/admin-access', $controller(SettingsController::class, 'publicAdminSettings'));
    $app->get('/api/settings/admin-access/secure', $controller(SettingsController::class, 'secureAdminSettings'))->add($adminAuth);
    $app->put('/api/settings/admin-access', $controller(SettingsController::class, 'updateAdminSettings'))->add($adminAuth);
    $app->post('/api/admin/login', $controller(AuthController::class, 'login'));

    // SEO meta
    $app->get('/api/seo-meta', $controller(SeoMetaController::class, 'index'))->add($adminAuth);
    $app->put('/api/seo-meta', $controller(SeoMetaController::class, 'upsert'))->add($adminAuth);

    // Portfolio service-tab SEO meta
    $app->get('/api/portfolio-service-tab-seo-meta', $controller(PortfolioServiceTabSeoMetaController::class, 'index'))->add($adminAuth);
    $app->put('/api/portfolio-service-tab-seo-meta', $controller(PortfolioServiceTabSeoMetaController::class, 'upsert'))->add($adminAuth);

    // Services
    $app->get('/api/services', $controller(ServiceController::class, 'index'));
    $app->get('/api/services/minimal', $controller(ServiceController::class, 'indexMinimal'));
    $app->get('/api/services/{identifier}', $controller(ServiceController::class, 'show'));
    $app->post('/api/services', $controller(ServiceController::class, 'store'))->add($adminAuth);
    $app->put('/api/services/{id}', $controller(ServiceController::class, 'update'))->add($adminAuth);
    $app->delete('/api/services/{id}', $controller(ServiceController::class, 'destroy'))->add($adminAuth);

    // Service Cities
    $app->get('/api/service-cities', $controller(ServiceCityController::class, 'index'));
    $app->get('/api/service-cities/{id}', $controller(ServiceCityController::class, 'show'));
    $app->post('/api/service-cities', $controller(ServiceCityController::class, 'store'))->add($adminAuth);
    $app->put('/api/service-cities/{id}', $controller(ServiceCityController::class, 'update'))->add($adminAuth);
    $app->delete('/api/service-cities/{id}', $controller(ServiceCityController::class, 'destroy'))->add($adminAuth);

    // Portfolios
    $app->get('/api/portfolios', $controller(PortfolioController::class, 'index'));
    $app->get('/api/portfolios/slug/{slug}', $controller(PortfolioController::class, 'showBySlug'));
    $app->get('/api/portfolios/{id}', $controller(PortfolioController::class, 'show'));
    $app->post('/api/portfolios', $controller(PortfolioController::class, 'store'))->add($adminAuth);
    $app->put('/api/portfolios/{id}', $controller(PortfolioController::class, 'update'))->add($adminAuth);
    $app->delete('/api/portfolios/{id}', $controller(PortfolioController::class, 'destroy'))->add($adminAuth);

    // Blogs
    $app->get('/api/blogs', $controller(BlogController::class, 'index'));
    $app->get('/api/blogs/slug/{slug}', $controller(BlogController::class, 'showBySlug'));
    $app->get('/api/blogs/{id}', $controller(BlogController::class, 'show'));
    $app->post('/api/blogs', $controller(BlogController::class, 'store'))->add($adminAuth);
    $app->put('/api/blogs/{id}', $controller(BlogController::class, 'update'))->add($adminAuth);
    $app->delete('/api/blogs/{id}', $controller(BlogController::class, 'destroy'))->add($adminAuth);    

    // Blog Topics
    $app->get('/api/blog-topics', $controller(BlogTopicController::class, 'index'));
    $app->get('/api/blog-topics/{id}', $controller(BlogTopicController::class, 'show'));
    $app->post('/api/blog-topics', $controller(BlogTopicController::class, 'store'))->add($adminAuth);
    $app->put('/api/blog-topics/{id}', $controller(BlogTopicController::class, 'update'))->add($adminAuth);
    $app->delete('/api/blog-topics/{id}', $controller(BlogTopicController::class, 'destroy'))->add($adminAuth);

    // Testimonials
    $app->get('/api/testimonials', $controller(TestimonialController::class, 'index'));
    $app->post('/api/testimonials', $controller(TestimonialController::class, 'store'))->add($adminAuth);
    $app->put('/api/testimonials/{id}', $controller(TestimonialController::class, 'update'))->add($adminAuth);
    $app->delete('/api/testimonials/{id}', $controller(TestimonialController::class, 'destroy'))->add($adminAuth);

    // Contact queries
    $app->post('/api/contact-queries', $controller(ContactController::class, 'store'));
    $app->get('/api/contact-queries', $controller(ContactController::class, 'index'))->add($adminAuth);
    $app->patch('/api/contact-queries/{id}/status', $controller(ContactController::class, 'updateStatus'))->add($adminAuth);

    // Quote requests
    $app->post('/api/quote-requests', $controller(QuoteController::class, 'store'));
    $app->get('/api/quote-requests', $controller(QuoteController::class, 'index'))->add($adminAuth);
    $app->patch('/api/quote-requests/{id}/status', $controller(QuoteController::class, 'updateStatus'))->add($adminAuth);

    // Uploads
    $app->post('/api/uploads/images', $controller(UploadController::class, 'uploadImage'))->add($adminAuth);
};
