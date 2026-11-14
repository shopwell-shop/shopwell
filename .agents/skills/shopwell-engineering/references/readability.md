# Readability and contract ownership

Read this reference when writing algorithms, validation, transformations or helpers, or when reviewing code that is legal but difficult to maintain.
The examples below identify useful decisions in real source; they are not templates to copy wholesale.

## Learn the existing vocabulary

`src/Core/Framework/ContentSystem/NAMING.md` asks two questions of a class name: what is its subject, and what is its role?
`ContentLayoutDefinition` describes a persisted layout and a DAL definition; injected dependencies do not determine its name.
Names in a family vary the word that represents the actual difference.
Apply this reasoning in other domains using their vocabulary; do not spread ContentSystem's `Stored`/`Rendered` prefixes to unrelated models.

Use the established platform role where it applies: Definition/Entity/Collection for DAL, Constraint/ConstraintValidator for Symfony validation, Subscriber for an event subscriber.
For another role, check what the class actually promises.
A normalizer should produce a canonical form; a planner should return a plan rather than also persisting it.
ContentSystem's `docs/role-suffixes.md` documents these distinctions and also names existing misnomers that must not become precedents.

Avoid names such as `data`, `result`, `process`, `handle` or `util` when the reader needs the hidden domain distinction to follow the code.
A conventional framework method such as a message handler's `__invoke()` can keep its framework name; domain-specific decisions inside it should still be named.
Do not rename a published identifier as part of a readability cleanup without treating its contract explicitly.

## Put guarantees at the boundary that owns them

Read `coding-guidelines/core/invariants-by-construction.md` and `writing-code-for-static-analysis.md` when introducing checks or narrowing types.
External JSON, a request or a plugin declaration needs validation before internal code relies on it.
An interface assertion or PHPDoc does not validate runtime input.
Once construction establishes a guarantee, downstream code should use it rather than repeatedly guessing types or supplying fallback values.
Keep `unknown` at genuine untrusted or extensible boundaries and narrow it there; do not repeatedly erase and reconstruct a known domain type through generic records or serialization.
If a public extension contract uses another type, verify that type is part of the supported surface too.

`SlicedDistributionConfig::fromArray()` in `src/Core/Framework/ContentSystem/Layout/Element/Context/Distribution/` distinguishes an absent `sliceSize` from a present value of the wrong type.
Its private constructor and validating factories establish the positive size for all instances.
Consumers can safely distribute data without checking that bound again.
The factory has a reason to exist; this does not justify a DTO for every private handoff.

Write down the meaning of missing, null, empty, zero and false for a new contract.
Do not combine them with a truthiness test unless they intentionally mean the same thing.
Likewise distinguish an expected domain outcome, a malformed request, an unsupported extension identifier and internal corruption.
ContentSystem's `docs/principles/failure-and-loss.md` is a detailed example of those separate outcomes, including explicit exceptions to fail-fast behavior.
Its exact HTTP classification and strict declaration policy belong to that module.

## Prefer a sequence a reader can verify

`src/Core/Framework/MessageQueue/ScheduledTask/ScheduledTaskExecutor.php::execute()` exposes the execution decision, task lookup, eligibility check, running state, failure handling and rescheduling in order.
Small methods name persistence operations; the caller still shows the complete orchestration.
The historical compatibility path belongs to the scheduler's existing contract and is not a reason to add compatibility scaffolding to a new feature.

`src/Core/Framework/ContentSystem/Binding/Registry/ContentSystemBindingSpecificationRegistry.php::all()` uses ordinary loops and a named qualified identifier to assemble its map.
It makes duplicate handling visible and reports both owners instead of silently replacing a value.
Borrow its straightforward control flow; do not add a registry when the problem is just a local list lookup.

For a multi-stage algorithm:

1. Establish the accepted input and indexes needed by the operation.
2. Perform traversal or classification with descriptive state and explicit termination.
3. Compute reusable measurements or aggregates once at their proper scope.
4. Produce the final result from those named intermediate values.

A loop is often clearer when accumulating mutable state or traversing a graph.
`map`, `filter`, `some`, `every`, `reduce` and `Map` remain appropriate when they express one understandable operation.
Avoid embedding traversal, mutation, filtering, measurement and output construction inside one callback chain.
Expand nested conditional expressions when they hide the supported cases or make the reader mentally execute several branches.
Name intermediate formula terms by what they represent, including their coordinate space or unit when necessary.
Look for nested rescans, repeated array spreading and prefix reductions inside per-item work before claiming an algorithm scales.
Do not introduce a cache or indexing abstraction unless the lifetime and reuse justify it.

Define behavior for empty input, invalid references, duplicates, cycles and disconnected components when the algorithm can receive them.
If an earlier boundary rules them out, identify that guarantee; do not invent a silent repair in an internal calculation.
An editor may deliberately represent an invalid draft; that is a distinct input contract from an executable published model.

## Keep validation and display semantics understandable

Use positive, domain-specific predicate names such as `isValidDate` or `isMissingRequiredValue` rather than a vague `valid` or `empty` across unlike fields.
Use braced, multiline guards; separate independent rules with whitespace.
For a known closed set, a readable switch or explicit dispatch can make supported cases and unsupported behavior visible.
For plugin-defined variants, use the owning extension registry rather than hardcoding a closed union of every possible plugin name.
Do not add a registry only to replace a few simple branches.

Check existing validation and formatting facilities before adding regexes or formatters.
For example, Administration's `src/Administration/Resources/app/administration/src/core/service/validation.service.js::required()` accepts boolean true and does not trim whitespace strings.
Those semantics may not match a required human explanation field.
`src/Administration/Resources/app/administration/src/core/service/utils/format.utils.ts::currency()` receives a number, and the currency filter parses strings as numbers.
Do not use that conversion when a domain contract requires exact decimal-string precision without first proving its range is safe.
Calendar dates, timestamps in a user's timezone, and server storage dates also have different contracts.
Reuse an implementation only when those semantics match; document and test a necessary domain-specific difference.

Keep parsing/validation, serialization and localized display as distinct responsibilities.
They can remain in one small cohesive file if their caller and contract justify it; separating them does not automatically require three services or classes.
An error result should have a stable meaning and enough field context for the UI to show it.
Do not silently treat a newly introduced field type as an existing type merely because it reaches a default branch.

## Review more than formatting

Ask whether a maintainer can explain each branch, default and side effect from its local context.
Extract a helper when it names a meaningful rule or stage, not merely to shorten the visible method.
Keep helpers together when they change for the same reason; move them to shared code after identifying a genuine shared contract.
Use comments for the reason behind precision, ownership, ordering or failure behavior rather than narrating the syntax.
Do not replace real types with `any`, assertions or suppression baselines to quiet tooling.
Tests should pin observable behavior and invariants so a clearer implementation can replace the current one without rewriting every test.
