<?php
namespace Craft;

class PreparseFieldService extends BaseApplicationComponent
{
    /**
     * Returns element content with values set for its Preparse fields.
     *
     * @param $element BaseElementModel
     * @param $eventHandle string
     * @return array
     */
    public function getPreparseFieldsContent($element, $eventHandle)
    {
        $content = array();

        $fieldLayout = $element->getFieldLayout();

        if ($fieldLayout) {
            foreach ($fieldLayout->getFields() as $fieldLayoutField) {
                $field = $fieldLayoutField->getField();

                if ($field) {
                    $fieldType = $field->getFieldType();

                    if ($fieldType && $fieldType->getClassHandle() === 'PreparseField_Preparse') {

                        // only get field content for the right event listener
                        $isBeforeSave = $eventHandle == 'onBeforeSave';
                        $parseBeforeSave = (bool) $fieldType->getSettings()->parseBeforeSave;

                        if ($isBeforeSave === $parseBeforeSave) {
                            $fieldType->element = $element;

                            $fieldValue = $this->parseField($fieldType);

                            if ($fieldValue!==null) {
                                $content[$field->handle] = $fieldValue;
                            }
                        }
                    }
                }
            }
        }

        return $content;
    }

    /**
     * Renders a Preparse field's template.
     *
     * @param $fieldType PreparseField_PreparseFieldType
     * @return string|null
     */
    public function parseField($fieldType)
    {
        $fieldTwig = $fieldType->getSettings()->fieldTwig;

        $element = $fieldType->element;
        $elementType = $element->getElementType();
        $elementTemplateName = strtolower($elementType);

        // set generateTransformsBeforePageLoad = true
        $configService = craft()->config;
        $generateTransformsBeforePageLoad = $configService->get('generateTransformsBeforePageLoad');
        $configService->set('generateTransformsBeforePageLoad', true);

        // save cp template path and set to site templates
        if (craft()->getBuild()<2778) {
            $oldPath = craft()->path->getTemplatesPath();
            craft()->path->setTemplatesPath(craft()->path->getSiteTemplatesPath());
        } else {
            $oldMode = craft()->templates->getTemplateMode();
            craft()->templates->setTemplateMode(TemplateMode::Site);
        }

        // render value from the field template
        try {
            $fieldValue = craft()->templates->renderString($fieldTwig, array($elementTemplateName => $element));
        } catch (\Exception $e) {
            PreparseFieldPlugin::log('Couldn’t render value for element with id “'.$element->id.'” and preparse field “' .
                $fieldType->model->handle.'” ('.$e->getMessage().').', LogLevel::Error);
        }

        // restore cp template paths
        if (craft()->getBuild()<2778) {
            craft()->path->setTemplatesPath($oldPath);
        } else {
            craft()->templates->setTemplateMode($oldMode);
        }

        // set generateTransformsBeforePageLoad back to whatever it was
        $configService->set('generateTransformsBeforePageLoad', $generateTransformsBeforePageLoad);

        if (!isset($fieldValue)) {
            return null;
        }

        return $fieldValue;
    }
}
