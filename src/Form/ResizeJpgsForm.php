<?php
/**
 * Created by PhpStorm.
 * User: d430974
 * Date: 2018-12-14
 * Time: 14:56
 */

namespace Drupal\image_tools\Form;


use Drupal\Core\Form\FormBase;
use \Drupal\Core\Form\FormStateInterface;


class ResizeJpgsForm extends FormBase
{
    public function getFormId()
    {
        return 'image_tools_resize_jpgs_form';
    }

    public function buildForm(array $form, FormStateInterface $form_state, $max_width = 2048, $include_png = false)
    {
        $form['#attributes']['class'][] = 'heyho';

        $form['include_png'] = array(
            '#type' => 'checkbox',
            '#title' => t('Include PNGs'),
            '#default_value' => $include_png,
        );

        $form['max_width'] = array(
            '#type' => 'number',
            '#title' => t('Max Width'),
            '#default_value' => $max_width,
            '#min' => 1
        );

        $form['actions']['#type'] = 'actions';
        $form['actions']['submit'] = array(
            '#type' => 'submit',
            '#value' => $this->t('Search'),
            '#button_type' => 'primary',
        );

        return $form;
    }

    public function submitForm(array &$form, FormStateInterface $form_state)
    {

    }


    /**
     * Currently not called...
     *
     * @param array $form
     * @param FormStateInterface $form_state
     */
    public function validateForm(array &$form, FormStateInterface $form_state)
    {
        $max_width = $form_state->getValue('max_width');

        if($max_width <= 0) {
            $form_state->setErrorByName('max_width', $this->t('The max width must be at least 1 Pixel.'));
        }

    }
}