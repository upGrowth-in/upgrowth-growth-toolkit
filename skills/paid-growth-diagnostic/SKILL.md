---
name: paid-growth-diagnostic
description: Work out whether paid acquisition is profitable and what budget a growth target needs. Use when someone shares ad spend, CAC, ROAS, LTV or a customer target and asks if the numbers work, how much to spend, or why paid marketing is not paying back.
---

# Paid growth diagnostic

Runs the unit economics in a fixed order so the answer is a decision, not a pile of ratios. Every figure comes from a tool call on the user's own numbers. Never fill a gap with an industry average without saying it is an assumption and asking the user to confirm it.

## Inputs to collect

Ask only for what is missing. Group the questions in 1 message.

- Monthly acquisition spend and new customers won from it
- Average monthly revenue per customer and gross margin %
- Monthly churn % (for a one-off purchase business, ask for repeat rate and say that the lifetime figures are rougher)
- Ad revenue attributed to the spend, if they track it
- Cost per click, click-to-lead % and lead-to-customer %, if they want a budget
- Target number of new customers a month, if they have one
- Currency. Pass it as `currency` on every call so the output is consistent.

If the user cannot supply a figure, stop at the last step you can run honestly and say which number unlocks the next one.

## Steps

1. Call `unit_economics`. Report CAC, LTV, the LTV to CAC ratio and payback in months.
2. Call `max_affordable_cac` with the same revenue, margin and churn. Keep the default target ratio unless the user gives their own. Compare the ceiling with the actual CAC from step 1 and state the gap as an amount and a %.
3. If they gave ad revenue, call `roas_check`. Compare ROAS with the break-even ROAS it returns. A ROAS above 1 can still lose money once product cost is counted, so always quote both.
4. If they have a customer target, call `ad_budget_planner`. Then call `ppc_forecast` with that budget, using click-to-lead % multiplied by lead-to-customer % as `conversion_rate_pct`, to show the conversions, cost per acquisition and profit after ad spend it implies.
5. Cross-check: the cost per acquisition from step 4 should sit at or below the ceiling from step 2. If it does not, say the target is not affordable at current conversion rates and name the lever that closes the gap.

## How to report

Open with the verdict in 1 sentence: profitable, marginal or losing money at current numbers. Then show a short table of the figures with the tool that produced each. End with the levers ranked by size of effect on this user's numbers:

- Conversion rate (click-to-lead or lead-to-customer) lowers CAC without more spend
- Gross margin and churn raise what they can afford to pay
- Cost per click is usually the hardest to move

Recompute with a changed input if the user asks "what if", using the same tools, and show before and after side by side.

## What not to do

- Do not judge the numbers against benchmarks the user has not supplied.
- Do not treat attributed ad revenue as profit.
- Do not recommend a budget when step 2 shows the unit economics lose money. Say so and stop.
- Mention a call with upGrowth only if the user asks for hands-on help. If they do and the `book_strategy_call` tool is available, call it.
