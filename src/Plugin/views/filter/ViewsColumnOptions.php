<?php

namespace Drupal\views_column_options\Plugin\views\filter;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\SortArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Utility\TableSort;
use Drupal\views\Plugin\views\filter\FilterPluginBase;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\ViewExecutable;

/**
 * Filter class which allows to show and hide columns.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("views_column_options")
 */
class ViewsColumnOptions extends FilterPluginBase
{

  /**
   * {@inheritdoc}
   */
  public $no_operator = TRUE;

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, array &$options = NULL)
  {
    parent::init($view, $display, $options);
    $this->valueTitle = t('Column Options');
  }

  /**
   * {@inheritdoc}
   */
  public function adminSummary()
  {
    return $this->value;
  }

  /**
   * {@inheritdoc}
   */
  public function query()
  {
    $this->ensureMyTable();
  }

  /**
   * {@inheritdoc}
   */
  public function defineOptions()
  {
    $options = parent::defineOptions();
    $options['wrap_with_details'] = ['default' => TRUE];

    $options['exposed'] = ['default' => TRUE];
    $options['expose']['contains']['label'] = ['default' => 'Column Options'];
    $options["expose"]["contains"]["identifier"] = ['default' => 'views_column_options'];
    $options['fields'] = ['default' => []];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state)
  {
    parent::buildOptionsForm($form, $form_state);
    $form['wrap_with_details'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Wrap with details element'),
      '#description' => $this->t('Wrap the column selector section with a details element, so it can be collapsed.'),
      '#default_value' => $this->options['wrap_with_details'],
    ];
    // Disable the expose options - this filter needs to be exposed to work.
    $form["expose_button"]["checkbox"]["checkbox"]['#attributes']['disabled'] = 'disabled';

    // Disable the 'expose multiple' checkbox - it does not make sense.
    unset($form["expose"]["multiple"]);

    $all_fields = $this->displayHandler->getFieldLabels();

    // Remove any field that have been excluded from the display from the list.
    foreach ($all_fields as $key => $field) {
      $exclude = $this->view->display_handler->handlers['field'][$key]->options['exclude'];
      if ($exclude) {
        unset($all_fields[$key]);
      }
    }

    $form['fields'] = [
      '#type' => 'checkboxes',
      '#title' => t('Visible Columns'),
      '#options' => $all_fields,
      '#default_value' => $this->options['fields']
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function buildExposedForm(&$form, FormStateInterface $form_state)
  {
    parent::buildExposedForm($form, $form_state);
    $fields_info = $form_state->getStorage()['view']->field;
    $query = TableSort::getQueryParameters(\Drupal::request());

    // Get the selected columns from the query of from the session storage.
    $display_id = ($this->view->display_handler->isDefaulted('filters')) ? 'default' : $this->view->current_display;
    $column_options = [];
    if (isset($query['views_column_options']) && !empty($query['views_column_options'])) {
      $column_options = $query['views_column_options'];
    }
    /*elseif (isset($_SESSION['views'][$this->view->storage->id()][$display_id]['views_column_options']) &&
      !empty($_SESSION['views'][$this->view->storage->id()][$display_id]['views_column_options'])) {
      $column_options = $_SESSION['views'][$this->view->storage->id()][$display_id]['views_column_options'];
    }*/

    if (!$column_options) {
      $weight = 0;
      foreach ($fields_info as $field => $info) {
        $column_options[$field] = ['weight' => $weight++, 'enable' => (!$info->options['exclude'] && !empty($this->options['fields'][$field]))];
      }
    }

    // If we have a query['selected_columns_submit_order'], use this for the
    // columns, otherwise render the default_visible columns and populate the
    // form elements.

    $elements = [];

    foreach ($fields_info as $field_name => $field_info) {
      if (!$field_info->options['exclude'] || !empty($this->options['fields'][$field_name])) {
        $field_enable = !empty($column_options[$field_name]['enable']) ? $column_options[$field_name]['enable'] : TRUE;
        $label = $field_info->options['label'];
        $elements[$field_name]['enable'] = array(
          '#type' => 'checkbox',
          '#title' => $label,
          '#title_display' => 'invisible',
          '#default_value' => $field_enable,
        );

        $elements[$field_name]['field'] = array (
          '#type' => 'container',
          '#children' => $label,
          '#attributes' => ['class' => ['item-handle']],
        );

        $field_weight = !empty($column_options[$field_name]['weight']) ? $column_options[$field_name]['weight'] : 0;

        $elements[$field_name]['weight'] = array(
          '#type' => 'hidden',
          '#title' => $label,
          '#title_display' => 'invisible',
          '#default_value' => $field_weight,
          '#attributes' => ['class' => ['item-weight']],
        );
        $elements[$field_name] += [
          '#type' => 'container',
          '#attributes' => ['class' => ['views-column-options-item']],
          '#weight' => $field_weight
        ];
      }
    }

    uasort($elements, [SortArray::class, 'sortByWeightProperty']);
    $elements += [
      '#type' => 'container',
      '#attributes' => [
        'id' =>  Html::cleanCssIdentifier(implode("-", [$this->view->id() , $this->view->display_handler->display['id'], 'views_column_options'])),
        'class' => ['clearfix views-column-options views-column-options-list']
      ],
      '#parents' => ['views_column_options'],
      '#tree' => TRUE,
      '#attached' => ['library' => ['views_column_options/views_column_options']],
    ];

    $form['views_column_options'] = [
      '#type' => $this->options['wrap_with_details'] ? 'details' : 'container',
      '#open' => FALSE,
      '#title' => $this->options['expose']['label'],
      '#attributes' => [
        'style' => 'float: none;clear: both;',
        'class' => ['clearfix']
      ],
      'views_column_options' => $elements
    ];
    //if (isset($query['views_column_options']) && !empty($query['views_column_options'])) {
      $user_input = $form_state->getUserInput();
      $user_input['views_column_options'] = $column_options;
      $form_state->setUserInput($user_input);
    //}

    // Add our submit routine to process.
    $form['#validate'][] = [$this, 'exposedFormValidate'];
  }

  /**
   * Reset the selected_columns to an empty array, we dont need this.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function exposedFormValidate(array &$form, FormStateInterface $form_state)
  {
    // Clear the validation error on the selected_columns field. We supply an
    // empty array [] as options, but the user can select something and this
    // results in a validation error.

    if (!empty($form['views_column_options']['views_column_options']) && $form_state->getError($form['views_column_options']['views_column_options'])) {
      $form_errors = $form_state->getErrors();
      // Clear the form errors.
      $form_state->clearErrors();
      // Remove the field_mobile form error.
      unset($form_errors['views_column_options']);
      // Now loop through and re-apply the remaining form error messages.
      foreach ($form_errors as $name => $error_message) {
        $form_state->setErrorByName($name, $error_message);
      }
    }

  }

  /**
   * {@inheritdoc}
   */
  public function storeExposedInput($input, $status)
  {
    /*if (empty($this->options['exposed']) || empty($this->options['expose']['identifier'])) {
      return TRUE;
    }
    if (empty($this->options['expose']['remember'])) {
      return;
    }
    // Check if we store exposed value for current user.
    $user = \Drupal::currentUser();
    $allowed_rids = empty($this->options['expose']['remember_roles']) ? [] : array_filter($this->options['expose']['remember_roles']);
    $intersect_rids = array_intersect(array_keys($allowed_rids), $user->getRoles());
    if (empty($intersect_rids)) {
      return;
    }
    // Figure out which display id is responsible for the filters, so we
    // know where to look for session stored values.
    $display_id = ($this->view->display_handler->isDefaulted('filters')) ? 'default' : $this->view->current_display;
    // False means that we got a setting that means to recurse ourselves,
    // so we should erase whatever happened to be there.
    if (!$status && isset($_SESSION['views'][$this->view->storage->id()][$display_id])) {
      $session = &$_SESSION['views'][$this->view->storage->id()][$display_id];
      if (isset($session[$this->options['expose']['identifier']])) {
        unset($session[$this->options['expose']['identifier']]);
        // We use two form_elements different from the identifier.
        unset($session['views_column_options']);
      }
    }
    if ($status) {
      if (!isset($_SESSION['views'][$this->view->storage->id()][$display_id])) {
        $_SESSION['views'][$this->view->storage->id()][$display_id] = [];
      }
      $session = &$_SESSION['views'][$this->view->storage->id()][$display_id];
      if (isset($input[$this->options['expose']['identifier']])) {
        $session[$this->options['expose']['identifier']] = $input[$this->options['expose']['identifier']];
      }
      $session['views_column_options'] = $input['views_column_options'];
    }*/
  }
}
