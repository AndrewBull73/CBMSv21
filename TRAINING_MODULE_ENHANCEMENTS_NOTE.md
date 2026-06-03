# Training Module Enhancements Note

## Purpose

Capture additional ideas that could strengthen a future CBMSv21 training module beyond simple on-screen prompts.

This note is a design reference only. It is **not** for implementation yet.

## Recommended Enhancements

### 1. Role-Based Training Paths

Training should be organised by business role, for example:

- Strategic Framework User
- Strategic Framework Reviewer
- Strategic Framework Approver
- Reporting User
- Configuration Administrator
- System Administrator

This helps ensure users learn the parts of the system that are actually relevant to their role.

### 2. Scenario-Based Exercises

Training should be built around realistic business scenarios, not just isolated fields.

Examples:

- create a project
- enter activity budgets
- create a narrative
- record a fiscal risk
- submit a funding request
- review and approve a workflow item
- run and interpret a report

### 3. Trainer Mode

Provide a trainer-oriented capability that can:

- see learner progress
- restart a scenario
- move a learner to the next step
- identify where learners are struggling

This would be especially useful for classroom or guided rollout sessions.

### 4. Pause And Resume

Training scenarios should support:

- pause
- resume
- continue from last completed step

This is important because many workflows are too long to expect completion in one uninterrupted session.

### 5. Sandbox / Safe Training Context

Training should happen in a clearly identified environment or mode where:

- users know they are not in production
- training data can be reset easily
- mistakes do not create business confusion

### 6. Hint Levels

Training could support different levels of guidance, for example:

- full walkthrough
- light hints
- challenge mode

This allows the same scenario to support beginners and more confident users.

### 7. Knowledge Checks

Add small learning checks between steps or at the end of a scenario, for example:

- what screen should be used next
- why a specific selection matters
- which workflow role should act at this point

These do not need to be formal exams, but they help confirm understanding.

### 8. Completion Tracking

Track completion of training scenarios, including:

- user
- role
- scenario
- completion date
- completion status
- whether help was required

This becomes useful for rollout monitoring and refresher planning.

### 9. Common Mistake Guidance

Training Mode should be able to explain common mistakes when possible, for example:

- wrong field selected
- required step skipped
- invalid workflow action
- wrong scope or context

This would make training more supportive and reduce confusion.

### 10. Printable / Downloadable Training Packs

Provide supporting reference materials such as:

- quick start guides
- step-by-step worksheets
- trainer handouts
- summary reference sheets

This is useful for both formal training sessions and post-training reinforcement.

### 11. Glossary And Term Help

Include lightweight help for important business terms, for example:

- sector
- program
- Org Unit
- funding type
- workflow stage
- fiscal risk

This would be particularly valuable where users have mixed technical and business backgrounds.

### 12. Demo Walkthrough Mode

In addition to active practice, consider a passive demonstration mode where:

- the scenario is explained step by step
- the system highlights what would happen
- the trainee can observe before doing the exercise themselves

### 13. Post-Training Quick Guides

After a training scenario is completed, provide short reference notes that users can return to later for common tasks.

### 14. Training Analytics

In a later phase, training data could help answer:

- where users struggle most
- which screens are most confusing
- which scenarios take longest
- which roles need more support

This could improve both training and product design over time.

## Suggested Priority

If the training module is built in stages, the strongest early enhancements would be:

1. role-based training paths
2. scenario-based exercises
3. pause and resume
4. trainer mode
5. completion tracking
6. glossary / term help

## Related Notes

This note should be read together with:

- [TRAINING_MODE_GUIDED_PROMPTS_NOTE.md](C:/xampp82/htdocs/CBMSv21/TRAINING_MODE_GUIDED_PROMPTS_NOTE.md)
- [TESTING_READINESS_NOTE.md](C:/xampp82/htdocs/CBMSv21/TESTING_READINESS_NOTE.md)
- [SCREEN_TEST_FRAMEWORK_DESIGN_NOTE.md](C:/xampp82/htdocs/CBMSv21/SCREEN_TEST_FRAMEWORK_DESIGN_NOTE.md)

## Summary

A strong training module should not only highlight fields. It should also support:

- realistic scenarios
- role-based learning
- progress tracking
- trainer support
- learning reinforcement

That will make it much more valuable during rollout, onboarding, and refresher learning.
