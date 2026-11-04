<?php
namespace modules;

use Craft;
use craft\guestentries\controllers\SaveController;
use craft\guestentries\events\SaveEvent;
use craft\elements\Entry;
use craft\events\DefineHtmlEvent;
use craft\events\RegisterCpNavItemsEvent;
use craft\events\RegisterElementActionsEvent;
use craft\helpers\Html;
use craft\helpers\Json;
use craft\events\RegisterTemplateRootsEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\web\twig\variables\Cp;
use craft\web\twig\variables\CraftVariable;
use craft\web\UrlManager;
use craft\web\View;
use modules\elements\actions\AcceptProposals;
use modules\services\ProposalAcceptor;
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
        $this->registerProposalAcceptance();

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
     * Propositions de superstars : « Accepter la proposition » crée la superstar en brouillon.
     * Bouton sur la fiche de la proposition + action en masse dans l'index de la section.
     */
    private function registerProposalAcceptance(): void
    {
        $canAccept = function(): bool {
            $user = Craft::$app->getUser()->getIdentity();
            $section = Craft::$app->getEntries()->getSectionByHandle('superstars');
            return $user && $section && $user->can("saveEntries:{$section->uid}");
        };

        Event::on(
            Entry::class,
            Entry::EVENT_REGISTER_ACTIONS,
            function(RegisterElementActionsEvent $event) use ($canAccept) {
                $section = Craft::$app->getEntries()->getSectionByHandle(ProposalAcceptor::PROPOSAL_SECTION);
                if ($section && $event->source === "section:{$section->uid}" && $canAccept()) {
                    $event->actions[] = AcceptProposals::class;
                }
            }
        );

        Event::on(
            Entry::class,
            Entry::EVENT_DEFINE_ADDITIONAL_BUTTONS,
            function(DefineHtmlEvent $event) use ($canAccept) {
                /** @var Entry $entry */
                $entry = $event->sender;
                if (
                    $event->static ||
                    !ProposalAcceptor::isProposal($entry) ||
                    $entry->getIsUnpublishedDraft() ||
                    $entry->getIsRevision() ||
                    ProposalAcceptor::isAccepted($entry->getCanonical()) ||
                    !$canAccept()
                ) {
                    return;
                }

                $buttonId = Html::id('cwe-accept-proposal');
                $event->html .= Html::button('Accepter la proposition', [
                    'id' => $buttonId,
                    'class' => ['btn', 'secondary'],
                    'title' => 'Crée la superstar en brouillon à partir de cette proposition',
                ]);

                $js = <<<JS
(() => {
    const button = document.getElementById({$this->jsonEncode($buttonId)});
    if (!button) return;
    button.addEventListener('click', async () => {
        if (!confirm('Créer la superstar en brouillon à partir de cette proposition ?')) return;
        button.classList.add('loading');
        button.disabled = true;
        try {
            const {data} = await Craft.sendActionRequest('POST', 'my-module/proposals/accept', {
                data: {proposalId: {$this->jsonEncode($entry->getCanonicalId())}},
            });
            window.location.href = data.cpEditUrl;
        } catch (e) {
            Craft.cp.displayError(e?.response?.data?.message ?? 'La superstar n\'a pas pu être créée.');
            button.classList.remove('loading');
            button.disabled = false;
        }
    });
})();
JS;
                Craft::$app->getView()->registerJs($js);
            }
        );
    }

    private function jsonEncode(mixed $value): string
    {
        return Json::encode($value);
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
