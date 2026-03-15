<?php
declare(strict_types=1);
namespace RCS\WP\Formidable;

use Psr\Log\LoggerInterface;

/**
 * Base class for custom Formidable fields.
 */
abstract class CustomFormField
{
    public function __construct(
        protected string $fieldType,
        protected string $fieldName,
        protected LoggerInterface $logger
        )
    {
        add_filter('frm_available_fields', [$this, 'addToFieldList']);
        add_filter('frm_before_field_created', [$this, 'setFieldDefaults']);
        add_filter('frm_display_field_options', [$this, 'setDisplayFieldOptions']);
        add_filter('frm_update_field_options', [$this, 'updateFieldOptions'], 10, 3);
        add_filter('frm_html_label_position', [$this, 'htmlLabelPosition'], 10, 3);
        add_filter('frm_clean_' . $this->fieldType . '_field_options_before_update', [$this, 'cleanFieldOptionsBeforeUpdate'], 10, 2);

        add_action('frm_display_added_fields', [$this, 'displayFieldInBuilder']);
        add_action('frm_form_field_'.$this->fieldType, [$this, 'displayFieldOnForm'], 10, 3);
        add_action('frm_field_options_form', [$this, 'addFieldOptions'], 10, 3);
    }

    /**
     * Adds our field to the list of fields available for placement on a form.
     *
     * Filter handler for 'frm_available_fields'
     *
     * @param array<string, mixed> $fields Assocative array of fields
     *
     * @return array<string, mixed>
     */
    public function addToFieldList(array $fields): array
    {
        $fields[$this->fieldType] = array(
            'name' => $this->fieldName,
            'icon' => 'frm_icon_font frm_filter_icon', // Set the class for a custom icon here.
        );

        return $fields;
    }


    /**
     * Sets the defaults for the field before it is displayed on a new form.
     *
     * Filter handler for 'frm_before_field_created'
     *
     * @param array<string, mixed> $field_data
     *
     * @return array<string, mixed>
     */
    public function setFieldDefaults(array $field_data): array
    {
        if ( $field_data['type'] == $this->fieldType ) {
            $field_data['name'] = $this->fieldName . ' (select options under Field Options/Advanced)';

            $defaults = array(
                'custom_html' => '<div id="frm_field_[id]_container" class="frm_form_field form-field [error_class]"><div id="field_[key]_label" class="frm_primary_label">[field_name]</div>[input]</div>'
            );
            $defaults = array_merge($this->getFieldOptionsDefaults(), $defaults);

            foreach ( $defaults as $k => $v ) {
                $field_data['field_options'][ $k ] = $v;
            }
        }

        return $field_data;
    }


    /**
     *
     * @return array<string, mixed>
     */
    protected function getFieldOptionsDefaults(): array {
        return array();
    }

    /**
     * Set the options allowed for the field.
     *
     * Filter handler for 'frm_display_field_options'
     *
     * @param array<string, mixed> $settings
     *
     * @return array<string, mixed>
     */
    public function setDisplayFieldOptions (array $settings): array
    {
        if ($settings['type'] == $this->fieldType) {
            $settings['css'] = false;
            $settings['label'] = false;  // allowed to change field label
            $settings['required'] = false;
            $settings['description'] = false;
            $settings['label_position'] = false;
            $settings['visibility'] = false;
            $settings['default'] = false;       // Hid Default Value field
            $settings['default_value'] = false;
            $settings['logic'] = false;         // Hide Conditional field
        }

        return array_merge($this->getDisplayFieldOptions(), $settings);
    }


    /**
     * @return array<string, mixed>
     */
    protected function getDisplayFieldOptions(): array {
        return array();
    }

    /**
     * Generate output for field options that appear when editing a field in the Form Builder.
     *
     * Action handler for 'frm_field_options_form'
     *
     * @param array<string, mixed> $field Details for the instance of the field
     * @param mixed[] $display A list of field options that should be shown for the current field.
     * @param mixed[] $values Details about the form.
     */
    public function addFieldOptions(array $field, array $display, array $values): void
    {
    }

    /**
     * Update options for the field.
     *
     * Filter handler for 'frm_update_field_options'
     *
     * @param array<string, mixed> $field_options Default field options
     * @param \stdClass $field Properties for the field
     * @param array<string, mixed> $values Values
     *
     * @return array<string, mixed> Updated field options
     */
    public function updateFieldOptions(array $field_options, \stdClass $field, array $values): array
    {
        if ($field->type == $this->fieldType) {

            $defaults = $this->getDisplayFieldOptions();

            foreach ($defaults as $opt => $default) {
                $field_options[ $opt ] = $values['field_options'][ $opt . '_' . $field->id ] ?? $default;
            }
        }

        return $field_options;
    }


    /**
     * Display the field in the Builder view
     *
     * Action handler for 'frm_display_added_fields'
     *
     * @param array<string, mixed> $field Data for a particular field.
     */
    public function displayFieldInBuilder (array $field): void
    {
        if ( $field['type'] == $this->fieldType ) {
            ?>
            <div class="frm_html_field_placeholder">
                <div class="howto button-secondary frm_html_field">
                    Placeholder for the <?php echo $this->fieldName; ?>.
                </div>
            </div>
             <?php
        }
    }


    /**
     * Display the field on the form.
     *
     * Action handler for 'frm_form_field_<$this->fieldType>'
     *
     * @param array<string, mixed> $field The field options
     * @param string $field_name the name of the field
     * @param array<string, mixed> $atts Attributes
     */
    abstract public function displayFieldOnForm (array $field, string $field_name, array $atts): void;

    protected function isFormidableEntriesPage(): bool
    {
        return \FrmAppHelper::is_admin() && current_user_can( 'frm_edit_entries' ) && \FrmAppHelper::is_admin_page('formidable-entries');
    }

    /**
     *
     * @param string $position
     * @param array<string, mixed> $field
     * @param \stdClass $form
     *
     * @return string
     */
    public function htmlLabelPosition(string $position, array $field, \stdClass $form): string
    {
        if ($field['type'] == $this->fieldType &&
            !$this->isFormidableEntriesPage()) {
            $position = 'none';
        }

        return $position;
    }

    /**
     *
     * @param array<string, mixed> $values
     * @param int|string $id
     *
     * @return array<string, mixed>
     */
    public function cleanFieldOptionsBeforeUpdate(array $values, int|string $id): array
    {
        return $values;
    }
}
