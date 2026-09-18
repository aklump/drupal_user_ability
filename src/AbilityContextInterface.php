<?php

namespace Drupal\user_ability;

/**
 * Marker for whatever a site's checks need beyond the account.
 *
 * Deliberately empty — a group, a node, a taxonomy term are all site
 * concepts, not generic ones. Each site declares its own concrete shape
 * implementing this interface.
 *
 * Examples:
 * @code
 * // A context wrapping a specific entity.
 * final class NodeContext implements AbilityContextInterface {
 *   public function __construct(
 *     public readonly ?NodeInterface $node = NULL,
 *   ) {}
 * }
 *
 * // A multi-entity or domain context.
 * final class GroupContentContext implements AbilityContextInterface {
 *   public function __construct(
 *     public readonly ?GroupInterface $group = NULL,
 *     public readonly ?NodeInterface $node = NULL,
 *   ) {}
 * }
 *
 * // A context carrying scalar or relational criteria.
 * final class DocumentContext implements AbilityContextInterface {
 *   public function __construct(
 *     public readonly ?int $documentId = NULL,
 *     public readonly ?string $targetStatus = NULL,
 *   ) {}
 * }
 * @endcode
 */
interface AbilityContextInterface {}
