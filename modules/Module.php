<?php
namespace modules;

use Craft;
use craft\guestentries\controllers\SaveController;
use craft\guestentries\events\SaveEvent;
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
}
