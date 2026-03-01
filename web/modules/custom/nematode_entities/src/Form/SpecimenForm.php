<?php

declare(strict_types=1);

namespace Drupal\nematode_entities\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for Specimen entities.
 */
final class SpecimenForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    $result = parent::save($form, $form_state);
    $args = ['%label' => $this->entity->label()];
    switch ($result) {
      case SAVED_NEW:
        $this->messenger()->addStatus($this->t('New specimen %label has been created.', $args));
        $this->logger('nematode_entities')->notice('New specimen %label has been created.', $args);
        break;

      case SAVED_UPDATED:
        $this->messenger()->addStatus($this->t('The specimen %label has been updated.', $args));
        $this->logger('nematode_entities')->notice('The specimen %label has been updated.', $args);
        break;

      default:
        throw new \LogicException('Could not save the entity.');
    }
    $form_state->setRedirectUrl($this->entity->toUrl('collection'));
    return $result;
  }

}
