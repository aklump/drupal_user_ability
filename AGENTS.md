# AGENTS.md

Instructions for this module's segment-composition mechanism:
`UserSegmentInterface`, `UserSegmentResolverInterface`,
`UserSegmentAwareTrait`.

## The resolver invariant

`UserSegmentResolverInterface::resolve()` must return `allowed()` or
`neutral()` — **never `forbidden()`**. Forbidden is reserved for a caller's
own veto via `UserSegmentAwareTrait::userIsNone()`, so one segment's "no"
can never poison the `orIf()` chain inside `userIsAny()`.

## There is deliberately no `userIsEvery()`

`UserSegmentAwareTrait` offers `userIsAny()` (an OR of segments) and
`userIsNone()` (a veto). It does not offer an AND-of-segments helper,
because no consumer has needed one yet.

Beware: "and" in a rule sentence is usually still an OR — "Leaders,
administrators and buyers can approve the order" lists three audiences who
each qualify alone. Add the AND helper only for a rule that requires one
person to be in two segments **at once**.

Until then, an AND is expressible at the call site without the helper:

    $this->userIsAny($u, $c, A)->andIf($this->userIsAny($u, $c, B));

If the rule does arrive, name it `userIsEvery()`, not `userIsAll()` —
`userIsAny()`/`userIsAll()` differ by one character and mean opposite
things; `userIsAny()`/`userIsEvery()` do not. The implementation:

    protected function userIsEvery(UserInterface $user, AbilityContextInterface $context, UserSegmentInterface ...$segments): AccessResult {
      $result = AccessResult::allowed();
      foreach ($segments as $segment) {
        $result = $result->andIf($this->getSegmentResolver()->resolve($user, $segment, $context));
        if (!$result->isAllowed()) {
          return $result;   // later segments never evaluated
        }
      }
      return $result;
    }

It depends on the resolver invariant above holding: if `resolve()` ever
returned `forbidden()`, one segment's forbidden would hard-veto the whole
`andIf()` chain and `userIsEvery()` would answer "forbidden" where it
should answer "no grant".
