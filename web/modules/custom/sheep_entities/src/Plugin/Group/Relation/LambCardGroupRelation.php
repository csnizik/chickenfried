<?php

declare(strict_types=1);

namespace Drupal\sheep_entities\Plugin\Group\Relation;

use Drupal\Core\Form\FormStateInterface;
use Drupal\group\Plugin\Group\Relation\GroupRelationBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\group\Plugin\Attribute\GroupRelationType;

/**
 * Provides a group relation for Lamb Card entities.
 */
#[GroupRelationType(
    id: 'sheep_entities_lamb_card',
    label: new TranslatableMarkup('Group lamb card'),
    description: new TranslatableMarkup('Adds lamb card entities to a group and optionally control view access.'),
    entity_type_id: 'sheep_entities_lamb_card',
    entity_access: TRUE,
)]
final class LambCardGroupRelation extends GroupRelationBase {

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
