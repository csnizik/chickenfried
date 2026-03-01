<?php

declare(strict_types=1);

namespace Drupal\nematode_entities\Plugin\Group\Relation;

use Drupal\Core\Form\FormStateInterface;
use Drupal\group\Plugin\Group\Relation\GroupRelationBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\group\Plugin\Attribute\GroupRelationType;

/**
 * Provides a group relation for Specimen entities.
 */
#[GroupRelationType(
  id: 'nematode_entities_specimen',
  label: new TranslatableMarkup('Group specimen'),
  description: new TranslatableMarkup('Adds specimen entities to a group and optionally control view access.'),
  entity_type_id: 'nematode_entities_specimen',
  entity_access: TRUE,
)]
final class SpecimenGroupRelation extends GroupRelationBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    $config = parent::defaultConfiguration();
    $config['entity_cardinality'] = 1;
    return $config;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    // Copying this behavior from the GroupNode relation. See
    // the group module's submodule gnode.
    $info = $this->t("This field has been disabled by the plugin to guarantee the functionality that's expected of it.");
    $form['entity_cardinality']['#disabled'] = TRUE;
    $form['entity_cardinality']['#description'] .= '<br/><em>' . $info . '</em>';

    return $form;
  }

}
