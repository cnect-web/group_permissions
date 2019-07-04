<?php

namespace Drupal\group_permissions\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Checks if an entity reference field has a unique value.
 *
 * @Constraint(
 *   id = "UniqueReferenceField",
 *   label = @Translation("Unique reference field constraint", context = "Validation"),
 * )
 */
class UniqueEntityReferenceConstraint extends Constraint {

  public $message = 'A @entity_type with @field_name %value already exists.';

  /**
   * {@inheritdoc}
   */
  public function validatedBy() {
    return '\Drupal\group_permissions\Plugin\Validation\Constraint\UniqueReferenceFieldValidator';
  }

}
