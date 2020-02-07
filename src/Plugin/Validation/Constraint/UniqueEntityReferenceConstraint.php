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
 *
 * @see https://www.drupal.org/project/drupal/issues/2973455
 */
class UniqueEntityReferenceConstraint extends Constraint {

  /**
   * The default violation message.
   *
   * @var string
   */
  public $message = 'A @entity_type with @field_name %value already exists.';

  /**
   * {@inheritdoc}
   */
  public function validatedBy() {
    return '\Drupal\group_permissions\Plugin\Validation\Constraint\UniqueReferenceFieldValidator';
  }

}
