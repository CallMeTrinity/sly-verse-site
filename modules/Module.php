<?php
namespace modules;

use Craft;
use craft\guestentries\controllers\SaveController;
use craft\guestentries\events\SaveEvent;
use craft\events\RegisterCpNavItemsEvent;
use craft\events\RegisterTemplateRootsEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\web\twig\variables\Cp;
use craft\web\twig\variables\CraftVariable;
use craft\web\UrlManager;
use craft\web\View;
use modules\variables\CweVariable;
use yii\base\Event;

/**
 * Custom module class.
 *
 * This class will be available throughout the system via:
 * `Craft::$app->getModule('my-module')`.
 *
 * You can change its module ID ("my-module") to something else from
 * config/app.php.
 *
 * If you want the module to get loaded on every request, uncomment this line
 * in config/app.php:
 *
 *     'bootstrap' => ['my-module']
 *
 * Learn more about Yii module development in Yii's documentation:
 * http://www.yiiframework.com/doc-2.0/guide-structure-modules.html
 */
class Module extends \yii\base\Module
{
    /**
     * Initializes the module.
     */
    public function init()
    {
        // Set a @modules alias pointed to the modules/ directory
        Craft::setAlias('@modules', __DIR__);

        // Set the controllerNamespace based on whether this is a console or web request
        if (Craft::$app->getRequest()->getIsConsoleRequest()) {
            $this->controllerNamespace = 'modules\\console\\controllers';
        } else {
            $this->controllerNamespace = 'modules\\controllers';
        }

        parent::init();

        $this->registerShowGenerator();

        // craft.cwe : helpers Twig du site (catégories de lutteurs, champions actuels...)
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function(Event $event) {
                $event->sender->set('cwe', CweVariable::class);
            }
        );

        // Honeypot : les bots remplissent le champ caché "website" du formulaire
        // de proposition de superstar ; on marque alors la soumission comme spam
        // (guest-entries répond "succès" sans rien enregistrer)
        if (class_exists(SaveController::class)) {
            Event::on(
                SaveController::class,
                SaveController::EVENT_BEFORE_SAVE_ENTRY,
                function(SaveEvent $event) {
                    if (Craft::$app->getRequest()->getBodyParam('website')) {
                        $event->isSpam = true;
                    }
                }
            );
        }
    }

    /**
     * Générateur de show : page CP /admin/show-generator + entrée de menu sous « Entrées ».
     */
    private function registerShowGenerator(): void
    {
        Event::on(
            View::class,
            View::EVENT_REGISTER_CP_TEMPLATE_ROOTS,
            function(RegisterTemplateRootsEvent $event) {
                $event->roots['cwe'] = __DIR__ . '/templates';
            }
        );

        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function(RegisterUrlRulesEvent $event) {
                $event->rules['show-generator'] = 'my-module/show-generator/index';
            }
        );

        Event::on(
            Cp::class,
            Cp::EVENT_REGISTER_CP_NAV_ITEMS,
            function(RegisterCpNavItemsEvent $event) {
                $section = Craft::$app->getEntries()->getSectionByHandle('shows');
                $user = Craft::$app->getUser()->getIdentity();
                if (!$section || !$user || !$user->can("saveEntries:{$section->uid}")) {
                    return;
                }

                $item = [
                    'url' => 'show-generator',
                    'label' => 'Générateur de show',
                    'icon' => 'wand-magic-sparkles',
                ];

                // Juste après « Entrées » (url content/… en Craft 5) : c'est là qu'on gère les shows
                $position = count($event->navItems);
                foreach ($event->navItems as $i => $navItem) {
                    $url = $navItem['url'] ?? '';
                    if ($url === 'entries' || str_starts_with($url, 'content/')) {
                        $position = $i + 1;
                    }
                }
                array_splice($event->navItems, $position, 0, [$item]);
            }
        );
    }
}
