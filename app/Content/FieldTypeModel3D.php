<?php

namespace App\Content; 

use App\Field;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Model3D;
use App\GenericFile;

class FieldTypeModel3D {

    use FieldTypeTrait;

    private $template_add = 'admin.space.content.field_model3d_add';
    private $template_edit = 'admin.space.content.field_model3d_edit';
    private $template_add_script = 'public/assets/admin/space/content/js/field_model_add.js';
    private $template_edit_script = 'public/assets/admin/space/content/js/field_model_edit.js';
    private $storage_path;


    /**
     * Create a new field type instance.
     *
     * @param String $storage_path
     *
     * @return void
     */
    public function __construct($storage_path) {

        $this->storagePath = $storage_path;
    }

  
    /**
     * Prepare template.
     *
     * @param String $field_key
     * @param Array $properties
     *
     * @return Array
     */
    public function prepare($field_key, $properties) {

        $field = [];
        $field = $properties;
        $field['#template'] = $this->template_add;
        $field['#template_script'] = $this->template_add_script;

        return $field;
    }


    /**
     * Load content.
     *
     * @param integer $content_id
     * @param String $field_key
     * @param Array $properties
     *
     * @return Array
     */
    public function load($content_id, $field_key, $properties) {

        $field_arr = [];

        $field_arr = $this->prepare($field_key, $properties);
        $field_arr['#template'] = $this->template_edit;
        $field_arr['#template_script'] = $this->template_edit_script;
        $field_arr['#content'] = array('#value' => null);
        $field_arr['#content'] = array('#id' => null);

        try {
            $field = Field::where('content_id', $content_id)->where('key', $field_key)->firstOrFail();
        } catch (ModelNotFoundException $e) {
            return $field_arr;
        }

        try {
            $model = Model3D::where('id', $field->data)->firstOrFail();
        } catch (ModelNotFoundException $e) {
            return $field_arr;
        }

        $genericFile = GenericFile::where('id', $model->file_id_preview)->first();

        $field_arr['#content']['#value'] = asset($genericFile->uri);
        $field_arr['#content']['#id'] = $model->id;

        return $field_arr;
    }


    /**
     * Get validation rules and messages.
     *
     * @param Request $request
     * @param Array $validation_rules_messages
     * @param String $field_key
     * @param Array $properties
     *
     * @return Array
     */
    public function get_validation_rules_messages($request, $validation_rules_messages, $field_key, $properties) {

        /* a proper mime type validation has been done during asset library upload */

        $file_extensions = '';
        foreach ($properties['#file-extension'] as $file_ext) {
            $file_extensions .= $file_ext . ',';
        }
        $file_extensions = substr($file_extensions, 0, -1);

        if ($request->input($field_key) != '' ) {

            $field_value = trim($request->input($field_key));
            $model = Model3D::where('id', $field_value)->first();

            /* file_id_0 */
            $genericFile = GenericFile::where('id', $model->file_id_0)->first();
            $path_parts = pathinfo($genericFile->filename);

            if ($path_parts['extension'] == Model3D::FILE_EXTENSION_MTL && !is_null($model->file_id_1)) { 
                /* file_id_1 */
                $genericFile = GenericFile::where('id', $model->file_id_1)->first();
                $path_parts = pathinfo($genericFile->filename);
            }

            $genericFilePreview = GenericFile::where('id', $model->file_id_preview)->first();

            $request->merge([$field_key => $path_parts['extension']]);

            /* needed if we want to store the file id instead of the extension */
            $request->request->add([$field_key . '__model3d_id' => $field_value]);
            /* needed if we want to retrieve the old input in case of validation error */
            $request->request->add([$field_key . '__model3d_src' => asset($genericFilePreview->uri)]);
        }

      
        if ($properties['#required']) {

            $validation_rules_messages['rules'] = array_add($validation_rules_messages['rules'], $field_key, 'required|in:' . $file_extensions);

            /* array_dot is flattens the array because $field_key . '.required' creates new array */
            $validation_rules_messages['messages'] = array_dot(array_add(
                $validation_rules_messages['messages'],
                $field_key . '.required',
                trans('fieldtype_model3d.validation_required', ['label' => $properties['#label']])
            ));

            /* array_dot flattens the array because $field_key . '.required' creates new array */
            $validation_rules_messages['messages'] = array_dot(array_add(
                $validation_rules_messages['messages'],
                $field_key . '.in',
                trans('fieldtype_model3d.validation_in', ['label' => $properties['#label'], 'file_extensions' => implode(', ', explode(',', $file_extensions))])
            ));

        } else {

            $validation_rules_messages['rules'] = array_add($validation_rules_messages['rules'], $field_key, 'in:' . $file_extensions);

            /* array_dot flattens the array because $field_key . '.required' creates new array */
            $validation_rules_messages['messages'] = array_dot(array_add(
                $validation_rules_messages['messages'],
                $field_key . '.in',
                trans('fieldtype_model3d.validation_in', ['label' => $properties['#label'], 'file_extensions' => implode(', ', explode(',', $file_extensions))])
            ));
        }

        return $validation_rules_messages;
    }


    /**
     * Save entry.
     *
     * @param int $space_id
     * @param int $content_id
     * @param String $field_key
     * @param String $type
     * @param Array $request_all
     *
     * @return True
     */
    public function save($space_id, $content_id, $field_key, $type, $request_all) {

        try {
            /* there is only one field key per content (id) */
            $field = Field::where('content_id', $content_id)->where('key', $field_key)->firstOrFail();

            if (array_has($request_all, $field_key . '__model3d_id')) {
                $field->data = $request_all[$field_key . '__model3d_id'];
                $field->save();
            } else {
                $field->delete();
            }

        } catch (ModelNotFoundException $e) {

            if (array_has($request_all, $field_key . '__model3d_id')) {
                $field = new Field;
                $field->content_id = $content_id;
                $field->key = $field_key;
                $field->type = $type;
                $field->data = $request_all[$field_key . '__model3d_id'];
                $field->save();
            }
        }

        return true;
    }


    /**
     * Delete content.
     *
     * @param integer $content_id
     * @param String $field_key
     * @param Array $properties
     *
     * @return Array
     */
    public function delete($content_id, $field_key, $properties) {

        try {
            $field = Field::where('content_id', $content_id)->where('key', $field_key)->firstOrFail();
        } catch (ModelNotFoundException $e) {
            return;
        }
        $field->delete();
    }


    /**
     * Validate theme config field.
     *
     * @param Array $field
     *
     * @return True if valid, false otherwise.
     */
    public function validateThemeFieldType($field) {

        $mandatoryKeys = [
            '#label' => 'string',
            '#description' => 'string',
            '#help' => 'string',
            '#required' => 'boolean',
            '#file-extension' => 'array'];
  
        return $this->validateFieldType($mandatoryKeys, $field);
    }


}