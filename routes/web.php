<?php
declare(strict_types=1);

use App\Core\Router;
use App\Controllers\{HomeController, ServiceController, BookingController, AuthController, AgentController, CronController, WebhookController};
use App\Controllers\Admin\{DashboardController, BookingAdminController, PlanningController, CrudController, SettingsController, NotificationAdminController, AuditController, CrmController, BusinessController, ServiceAdminController, MarketingController, SearchController};

$r = new Router();

// ---- Site public
$r->get('/', [HomeController::class, 'index']);
$r->get('/services', [ServiceController::class, 'index']);
$r->get('/services/{slug}', [ServiceController::class, 'show']);
$r->get('/a-propos', [HomeController::class, 'page']);
$r->get('/faq', [HomeController::class, 'faq']);
$r->get('/contact', [HomeController::class, 'contact']);
$r->post('/contact', [HomeController::class, 'contactSend'], ['throttle:contact,5,600']);
$r->get('/conditions-generales', [HomeController::class, 'page']);
$r->get('/confidentialite', [HomeController::class, 'page']);
$r->get('/hors-ligne', [HomeController::class, 'offline']);

// ---- Réservation
$r->get('/reserver', [BookingController::class, 'wizard']);
$r->get('/api/services/{id}/form', [BookingController::class, 'formDef']);
$r->get('/api/geo/{type}/{id}', [BookingController::class, 'geo']);
$r->post('/api/quote', [BookingController::class, 'quote'], ['throttle:api,120,60']);
$r->post('/api/availability/days', [BookingController::class, 'days'], ['throttle:api,120,60']);
$r->post('/api/availability/slots', [BookingController::class, 'slots'], ['throttle:api,120,60']);
$r->post('/reservation', [BookingController::class, 'store'], ['throttle:booking,10,600']);
$r->get('/reservation/confirmation/{ref}', [BookingController::class, 'confirmation']);
$r->get('/suivi', [BookingController::class, 'trackForm']);
$r->post('/suivi', [BookingController::class, 'track'], ['throttle:track,10,600']);
$r->get('/suivi/{ref}', [BookingController::class, 'trackShow']);
$r->get('/suivi/{ref}/calendrier', [BookingController::class, 'calendar']);
$r->post('/suivi/{ref}/annuler', [BookingController::class, 'trackCancel']);
$r->get('/suivi/{ref}/{key}', [BookingController::class, 'trackLink'], ['throttle:track,30,600']);   // lien personnel reçu par message : un clic suffit

// ---- Authentification
$r->get('/connexion', [AuthController::class, 'form']);
$r->post('/connexion', [AuthController::class, 'login'], ['throttle:login,8,300']);
$r->post('/deconnexion', [AuthController::class, 'logout']);

// ---- Réponses entrantes des fournisseurs (désinscription STOP) : authentifiées par signature ou jeton, sans session
$r->get('/webhooks/whatsapp', [WebhookController::class, 'whatsappVerify'], ['throttle:hook,120,60']);
$r->post('/webhooks/whatsapp', [WebhookController::class, 'whatsappReceive'], ['throttle:hook,300,60']);
$r->post('/webhooks/sms', [WebhookController::class, 'smsReceive'], ['throttle:hook,300,60']);

// ---- Tâches planifiées (cron HTTP protégé par jeton)
$r->get('/cron/run', [CronController::class, 'run'], ['throttle:cron,30,300']);
$r->get('/cron/install', [CronController::class, 'install']);   // usage unique, à retirer après l'installation

// ---- Administration
$r->get('/admin', [DashboardController::class, 'index'], ['perm:dashboard.view']);
$r->get('/admin/recherche', [SearchController::class, 'index'], ['perm:dashboard.view']);
$r->get('/admin/reservations', [BookingAdminController::class, 'index'], ['perm:bookings.view']);
$r->get('/admin/reservations/{id}', [BookingAdminController::class, 'show'], ['perm:bookings.view']);
$r->post('/admin/reservations/{id}/statut', [BookingAdminController::class, 'status'], ['perm:bookings.manage']);
$r->post('/admin/reservations/{id}/affecter', [BookingAdminController::class, 'assign'], ['perm:bookings.manage']);
$r->post('/admin/reservations/{id}/deplacer', [BookingAdminController::class, 'reschedule'], ['perm:bookings.manage']);
$r->post('/admin/reservations/{id}/note', [BookingAdminController::class, 'note'], ['perm:bookings.manage']);
$r->post('/admin/reservations/{id}/montant', [BookingAdminController::class, 'amount'], ['perm:bookings.manage']);
$r->post('/admin/api/creneaux', [BookingAdminController::class, 'slots'], ['perm:bookings.manage']);
$r->get('/admin/planning', [PlanningController::class, 'index'], ['perm:planning.view']);
$r->get('/admin/parametres', [SettingsController::class, 'index'], ['perm:settings.manage']);
$r->post('/admin/parametres', [SettingsController::class, 'save'], ['perm:settings.manage']);
$r->get('/admin/notifications', [NotificationAdminController::class, 'index'], ['perm:notifications.manage']);
$r->post('/admin/notifications/{id}/relancer', [NotificationAdminController::class, 'retry'], ['perm:notifications.manage']);
$r->post('/admin/notifications/relancer-tout', [NotificationAdminController::class, 'retryAll'], ['perm:notifications.manage']);
$r->get('/admin/audit', [AuditController::class, 'index'], ['perm:users.manage']);
// Fiche service (tarifs, simulateur, duplication)
$r->get('/admin/services/{id}', [ServiceAdminController::class, 'show'], ['perm:catalog.manage']);
$r->post('/admin/services/{id}/dupliquer', [ServiceAdminController::class, 'duplicate'], ['perm:catalog.manage']);
$r->post('/admin/services/{id}/simuler', [ServiceAdminController::class, 'simulate'], ['perm:catalog.manage']);
// CRM et gestion
$r->get('/admin/crm', [CrmController::class, 'pipeline'], ['perm:crm.manage']);
$r->get('/admin/crm/prospects/{id}', [CrmController::class, 'lead'], ['perm:crm.manage']);
$r->post('/admin/crm/prospects/{id}/statut', [CrmController::class, 'leadStatus'], ['perm:crm.manage']);
$r->post('/admin/crm/prospects/{id}/relance', [CrmController::class, 'leadFollowUp'], ['perm:crm.manage']);
$r->post('/admin/crm/prospects/{id}/convertir', [CrmController::class, 'leadConvert'], ['perm:crm.manage']);
$r->post('/admin/crm/prospects/{id}/echange', [CrmController::class, 'leadInteraction'], ['perm:crm.manage']);
$r->get('/admin/clients/{id}', [CrmController::class, 'customer'], ['perm:customers.manage']);
$r->post('/admin/clients/{id}/echange', [CrmController::class, 'customerInteraction'], ['perm:customers.manage']);
$r->post('/admin/clients/{id}/canal', [CrmController::class, 'customerChannel'], ['perm:customers.manage']);
$r->get('/admin/gestion', [BusinessController::class, 'index'], ['perm:finance.manage']);
// Marketing : canaux (administrateur), campagnes, WhatsApp sans API, liens suivis
$r->get('/admin/marketing', [MarketingController::class, 'index'], ['perm:marketing.manage']);
$r->get('/admin/marketing/canaux', [MarketingController::class, 'channels'], ['perm:settings.manage']);
$r->post('/admin/marketing/canaux/{section}', [MarketingController::class, 'channelSave'], ['perm:settings.manage']);
$r->post('/admin/marketing/canaux/{section}/tester', [MarketingController::class, 'channelTest'], ['perm:settings.manage', 'throttle:mktest,10,300']);
$r->get('/admin/marketing/campagnes', [MarketingController::class, 'campaigns'], ['perm:marketing.manage']);
$r->get('/admin/marketing/campagnes/nouveau', [MarketingController::class, 'campaignNew'], ['perm:marketing.manage']);
$r->post('/admin/marketing/campagnes', [MarketingController::class, 'campaignCreate'], ['perm:marketing.manage']);
$r->get('/admin/marketing/campagnes/{id}', [MarketingController::class, 'campaign'], ['perm:marketing.manage']);
$r->post('/admin/marketing/campagnes/{id}/lancer', [MarketingController::class, 'campaignLaunch'], ['perm:marketing.manage']);
$r->post('/admin/marketing/campagnes/{id}/lot', [MarketingController::class, 'campaignBatch'], ['perm:marketing.manage']);
$r->post('/admin/marketing/campagnes/{id}/annuler', [MarketingController::class, 'campaignCancel'], ['perm:marketing.manage']);
$r->post('/admin/marketing/campagnes/{id}/tester', [MarketingController::class, 'campaignTest'], ['perm:marketing.manage', 'throttle:mktest,10,300']);
$r->get('/admin/marketing/campagnes/{id}/whatsapp', [MarketingController::class, 'whatsapp'], ['perm:marketing.manage']);
$r->post('/admin/marketing/campagnes/{id}/whatsapp/{mid}', [MarketingController::class, 'whatsappMark'], ['perm:marketing.manage']);
$r->get('/admin/marketing/publicites', [MarketingController::class, 'ads'], ['perm:marketing.manage']);
$r->get('/admin/marketing/liens', [MarketingController::class, 'links'], ['perm:marketing.manage']);
// CRUD générique piloté par config/crud.php (les permissions sont vérifiées dans le contrôleur)
$r->get('/admin/crud/{type}', [CrudController::class, 'index'], ['auth']);
$r->get('/admin/crud/{type}/nouveau', [CrudController::class, 'create'], ['auth']);
$r->post('/admin/crud/{type}', [CrudController::class, 'store'], ['auth']);
$r->get('/admin/crud/{type}/{id}/modifier', [CrudController::class, 'edit'], ['auth']);
$r->post('/admin/crud/{type}/{id}', [CrudController::class, 'update'], ['auth']);
$r->post('/admin/crud/{type}/{id}/supprimer', [CrudController::class, 'destroy'], ['auth']);

// ---- Interface agent (PWA mobile)
$r->get('/agent', [AgentController::class, 'index'], ['perm:agent.missions']);
$r->get('/agent/mission/{id}', [AgentController::class, 'show'], ['perm:agent.missions']);
$r->post('/agent/mission/{id}/statut', [AgentController::class, 'status'], ['perm:agent.missions']);
$r->post('/agent/mission/{id}/note', [AgentController::class, 'note'], ['perm:agent.missions']);
$r->post('/agent/mission/{id}/photo', [AgentController::class, 'photo'], ['perm:agent.missions']);
$r->get('/photo/{id}', [AgentController::class, 'servePhoto'], ['auth']);

return $r;
