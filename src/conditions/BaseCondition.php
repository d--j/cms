<?php

namespace craft\conditions;

use Craft;
use craft\base\Component;
use craft\events\RegisterConditionRuleTypesEvent;
use craft\helpers\ArrayHelper;
use craft\helpers\Html;
use craft\helpers\Json;
use craft\helpers\UrlHelper;
use Illuminate\Support\Collection;

/**
 *
 * @property-read string $addRuleLabel
 * @property-read array $config
 * @property-read string $html
 * @property Collection $conditionRules
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.0
 */
abstract class BaseCondition extends Component implements ConditionInterface
{
    /**
     * @event DefineConditionRuleTypesEvent The event that is triggered when defining the condition rule types
     * @see conditionRuleTypes()
     * @since 4.0
     */
    public const EVENT_REGISTER_CONDITION_RULE_TYPES = 'registerConditionRuleTypes';

    /**
     * @var Collection
     */
    private Collection $_conditionRules;

    /**
     * @var string
     */
    public string $handle;

    /**
     * @inheritDoc
     */
    public function init(): void
    {
        if (!isset($this->_conditionRules)) {
            $this->_conditionRules = new Collection();
        }
    }

    /**
     * @inheritDoc
     */
    public function attributes()
    {
        $attributes = parent::attributes();
        $attributes[] = 'conditionRules';
        $attributes[] = 'handle';

        return $attributes;
    }

    /**
     * @return string
     */
    public function getAddRuleLabel(): string
    {
        return Craft::t('app', 'Add Rule');
    }

    /**
     * Returns the condition rule types for this condition
     *
     * Conditions should override this method instead of [[conditionRuleTypes()]]
     * so [[EVENT_DEFINE_CONDITION_RULE_TYPES]] handlers can modify the class-defined condition rule types.
     *
     * @return array
     * @since 4.0
     */
    abstract protected function defineConditionRuleTypes(): array;

    /**
     * Returns the condition rule types for this condition
     *
     * @return array Condition rule types
     */
    public function conditionRuleTypes(): array
    {
        $conditionRuleTypes = $this->defineConditionRuleTypes();

        // Give plugins a chance to modify them
        $event = new RegisterConditionRuleTypesEvent([
            'conditionRuleTypes' => $conditionRuleTypes,
        ]);

        $this->trigger(self::EVENT_REGISTER_CONDITION_RULE_TYPES, $event);

        return $event->conditionRuleTypes;
    }

    /**
     * Returns all available condition rule options for use in a select
     *
     * @return array Array of condition classes available to add to the condition
     */
    public function availableRuleTypesOptions(): array
    {
        $rules = $this->conditionRuleTypes();
        $options = [];
        foreach ($rules as $rule) {
            /** @var $rule string */
            $options[$rule] = $rule::displayName();
        }

        return $options;
    }

    /**
     * Returns all condition rules
     *
     * @return Collection
     */
    public function getConditionRules(): Collection
    {
        return $this->_conditionRules;
    }

    /**
     * Sets the condition rules
     *
     * @param BaseConditionRule[]|array $rules
     * @return void
     * @throws \yii\base\InvalidConfigException
     */
    public function setConditionRules(array $rules): void
    {
        $conditionRules = [];
        foreach ($rules as $rule) {
            if (is_array($rule)) {
                $conditionRules[] = Craft::$app->getConditions()->createConditionRule($rule);
            } elseif ($rule instanceof BaseConditionRule) {
                $conditionRules[] = $rule;
            }
        }

        $this->_conditionRules = new Collection($conditionRules);
    }

    /**
     * Add a Rule to the Condition
     *
     * @param BaseConditionRule $conditionRule
     */
    public function addConditionRule(BaseConditionRule $conditionRule): void
    {
        $conditionRule->setCondition($this);
        $this->_conditionRules->add($conditionRule);
    }

    /**
     * @return array
     */
    public function getConfig(): array
    {
        $config = [
            'type' => get_class($this),
            'handle' => $this->handle,
            'conditionRules' => []
        ];

        foreach ($this->getConditionRules() as $conditionRule) {
            $config['conditionRules'][] = $conditionRule->getConfig();
        }

        return $config;
    }

    /**
     * Renders the condition
     *
     * @return string
     */
    public function getHtml(): string
    {
        $conditionId = Html::namespaceId('condition', $this->handle);
        $indicatorId = Html::namespaceId('indicator', $this->handle);

        // Main Condition tag, and htmx inheritable options
        $html = Html::beginTag('form', [
            'id' => 'condition',
            'class' => 'pane',
            'hx-target' => '#' . $conditionId, // replace self
            'hx-swap' => 'outerHTML', // replace this tag with the response
            'hx-indicator' => '#' . $indicatorId, // ID of the spinner
        ]);

        // Loading indicator
        $html .= Html::tag('div', '', ['id' => 'indicator', 'class' => 'htmx-indicator spinner']);

        // Condition hidden inputs
        $html .= Html::hiddenInput('handle', $this->handle);
        $html .= Html::hiddenInput('type', get_class($this));
        $html = Html::namespaceHtml($html, $this->handle);

        $html .= Html::csrfInput();
        $html .= Html::hiddenInput('conditionLocation', $this->handle);

        $allRulesHtml = '';
        /** @var BaseConditionRule $rule */
        foreach ($this->_conditionRules as $rule) {
            // Rules types available
            $ruleClass = get_class($rule);
            $availableRules = $this->availableRuleTypesOptions();
            ArrayHelper::remove($availableRules, $ruleClass); // since we are adding it, remove it so we don't have duplicates
            $availableRules[$ruleClass] = $rule::displayName(); // should always be in the list since it is the current rule

            // Add rule type selector
            $ruleHtml = Craft::$app->getView()->renderTemplate('_includes/forms/select', [
                'name' => 'type',
                'options' => $availableRules,
                'value' => $ruleClass,
                'class' => '',
                'inputAttributes' => [
                    'hx-post' => UrlHelper::actionUrl('conditions/render'),
                ]
            ]);
            $ruleHtml = Html::tag('div', $ruleHtml, ['class' => 'condition-rule-type']);
            $ruleHtml .= Html::hiddenInput('uid', $rule->uid);

            // Get rule input html
            $ruleHtml .= Html::tag('div', $rule->getHtml(), ['class' => 'flex-grow']);

            // Add delete button
            $deleteButtonAttr = [
                'class' => 'delete icon',
                'hx-vals' => '{"uid": "' . $rule->uid . '"}',
                'hx-post' => UrlHelper::actionUrl('conditions/remove-rule'),
                'title' => Craft::t('app', 'Delete'),
            ];
            $deleteButton = Html::tag('a', '', $deleteButtonAttr);
            $ruleHtml .= Html::tag('div', $deleteButton);

            // Namespace the rule
            $ruleHtml = Craft::$app->getView()->namespaceInputs(function() use ($ruleHtml) {
                return $ruleHtml;
            }, "conditionRules[$rule->uid]");

            $draggableHandle = Html::tag('a', '', ['class' => 'move icon draggable-handle']);

            $allRulesHtml .= Html::tag('div',
                $draggableHandle . $ruleHtml,
                ['class' => 'flex draggable']
            );
        }

        $allRulesHtml = Html::namespaceHtml($allRulesHtml, $this->handle);

        // Sortable rules div
        $html .= Html::tag('div', $allRulesHtml, [
                'class' => 'sortable',
                'hx-post' => UrlHelper::actionUrl('conditions/render'),
                'hx-trigger' => 'end' // sortable library triggers this event
            ]
        );

        if (count($this->conditionRuleTypes()) > 0) {
            $addButtonAttr = [
                'class' => 'btn add icon',
                'hx-post' => UrlHelper::actionUrl('conditions/add-rule'),
            ];
            $addButton = Html::tag('button', $this->getAddRuleLabel(), $addButtonAttr);
            $html .= Html::tag('div', $addButton, ['class' => 'rightalign']);
        }

        $html .= Html::tag('div',
            Html::tag('pre', Json::encode($this->getConfig(), JSON_PRETTY_PRINT)),
            ['class' => 'pane']
        );

        $html .= Html::endTag('form');


        return $html;
    }

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        return [['conditionRules', 'safe']];
    }
}