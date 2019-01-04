<?php

namespace Shopsys\FrameworkBundle\Form;

use Fp\JsFormValidatorBundle\Factory\JsFormValidatorFactory as BaseJsFormValidatorFactory;
use Shopsys\FrameworkBundle\Model\Product\Parameter\ParameterValue;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Validator\Constraints;

class JsFormValidatorFactory extends BaseJsFormValidatorFactory
{
    /**
     * @param array $constraints
     * @return array
     */
    protected function parseConstraints(array $constraints)
    {
        $result = parent::parseConstraints($constraints);

        foreach ($result as $items) {
            foreach ($items as $item) {
                if ($item instanceof Constraints\All) {
                    $item->constraints = $this->parseConstraints($item->constraints);
                }
            }
        }

        return $result;
    }

    /**
     * @param \Symfony\Component\Form\FormInterface $form
     * @param array $viewTransformers
     *
     * @return array
     */
    protected function normalizeViewTransformers(FormInterface $form, array $viewTransformers)
    {
        $config = $form->getConfig();

        // Choice(s)ToBooleanArrayTransformer was deprecated in SF2.7 in favor of CheckboxListMapper and RadioListMapper
        if ($config->getType()->getInnerType() instanceof ChoiceType && $config->getOption('expanded')) {
            $namespace = 'Symfony\Component\Form\Extension\Core\DataTransformer\\';
            $transformer = $config->getOption('multiple')
                ? ['name' => $namespace . 'ChoicesToBooleanArrayTransformer']
                : ['name' => $namespace . 'ChoiceToBooleanArrayTransformer'];

            $transformer['choiceList'] = [];
            $optionsItemsThatAreNotInstanceOfParameterValue = [];
            foreach ($config->getOption('choices') as $formOptionChoiceItem) {
                if ($formOptionChoiceItem instanceof ParameterValue) {
                    $optionItemId = $formOptionChoiceItem->getId();
                    $transformer['choiceList'][$optionItemId] = $formOptionChoiceItem;
                } else {
                    $optionsItemsThatAreNotInstanceOfParameterValue[] = $formOptionChoiceItem;
                }
            }

            array_push($transformer['choiceList'], $optionsItemsThatAreNotInstanceOfParameterValue);

            array_unshift($viewTransformers, $transformer);
        }

        return $viewTransformers;
    }

    /**
     * @param \Symfony\Component\Form\Form $form
     * @return \Fp\JsFormValidatorBundle\Model\JsFormElement|null
     */
    public function createJsModel(Form $form)
    {
        /** @var \Symfony\Component\Form\Form|null $prototype */
        $prototype = $form->getConfig()->getAttribute('prototype');
        if ($prototype !== null && $prototype->getParent() === null) {
            $prototype->setParent($form);
        }

        return parent::createJsModel($form);
    }

    /**
     * @param string $route
     * @return string
     */
    protected function generateUrl($route)
    {
        if ($route === 'fp_js_form_validator.check_unique_entity') {
            $message = 'Unable to generate a URL for the named route "' . $route . '" as such route was removed as unsafe.';
            throw new RouteNotFoundException($message);
        }

        return parent::generateUrl($route);
    }
}
