## Challenges

Before I could begin the assessment, I spent a significant amount of the allocated time setting up my development environment on an older Windows machine. This reduced the time available to complete the full assessment.

I also only had access to the Claude free trial, which meant I was unable to connect Claude to the repository via MCP and use it to inspect the codebase directly.

Rather than dwell on these limitations, I focused on completing as much of the core functionality as possible and documenting the decisions I made, along with the next steps I would take given more time.

## Completed Tasks and Design Decisions

I initially set up Claude and several of the skills I would normally use when working on a real-world project. Although I was unable to fully utilise these within the development environment because of the limitations above, I have included them to demonstrate how I currently use AI to improve my development workflow.

One skill I find particularly useful is grill-me, especially when working on tasks where I want to reach a solution quickly. It effectively provides another perspective on the problem and allows me to challenge my own assumptions.

Before starting development, I provided the assessment brief to Claude while also working through the brief myself. My intention was to make sure that both the requirements and my understanding of them were aligned before implementation began. Combined with the grill-me skill, this is similar to discussing a problem with a team of developers before committing to an approach.

From an implementation perspective, I initially focused on being able to view, use and delete saved filters before moving on to the alert functionality.

I was not able to complete the UI for viewing alerts within the available time. However, the underlying approach would follow the same MVC pattern already demonstrated by the saved alerts functionality, so I am confident this would be a relatively straightforward extension.

For sending saved-search alerts, I chose to use a cron-based approach rather than an event-driven approach. I have documented the reasoning for this in documentation/SendSavedSearchAlerts.md.

The main consideration was reducing the potential for alert spam. An alternative would be to use events to populate an alert_pending table and then process and group those alerts. While this would be a valid and potentially more scalable approach, I felt it introduced unnecessary complexity for the requirements of this assessment.

### Create PR and other skills
I have also added a create-pr skill to demonstrate how I would approach creating pull requests in a real-world scenario. The aim is to keep PRs clean, consistent and easy for other developers to review.

Other skills I would consider adding to my workflow would focus on maintaining code quality and consistency. For example, but not limited to:

- Ensuring coding standards are followed consistently.
- Keeping comments concise, useful and easy to understand.
- Reviewing code for unnecessary complexity and avoiding introducing complex solutions simply for the sake of it. (Especially when using Opus on Ultra Code)
- Ensuring changes remain focused and relevant to the task being completed.
These skills would help maintain a consistent development workflow while allowing AI to assist with the parts of the development process where it can provide the most value.

## Next Steps
### Unit Testing

The main missing piece is unit test coverage.

Given more time, I would add tests around the core functionality, particularly the calls to the queue and alert-sending functionality. These external dependencies would be mocked so that the tests could focus on the behaviour of the application rather than the external services themselves.

### Viewing Alerts

As mentioned above, the ability to view alerts is not completed.

I would implement this using the same MVC approach already used elsewhere in the solution. I would not expect to need a new model, but I would extend the existing alerts model to record when an alert was sent.

## Optional Extras and Further Improvements
### Backfilling

I am a believer in giving customers as much control as possible.

Although an older match would not normally constitute a new alert, I think there is an opportunity to give customers the option to request previous results. For example, a saved alert could have an additional "Send these results" action, allowing the customer to receive the current matching results on demand.

### Duplicate Alerts
The solution keeps track of the listing_id on the alert model. This makes it straightforward to check whether a particular listing has already been sent to the customer and exclude it from future alerts.

This also helps address one of the requirements highlighted in the brief: reducing unnecessary or duplicate notifications.

### Search Criteria
I would use a price range rather than only a maximum price. I believe this is more familiar to customers and provides greater control over their searches.

I would also consider adding the number of bathrooms as a search criterion, along with a checklist of common property features such as:

Garden
Garage
Parking
Balcony
Conservatory
These would give customers more control over the properties they receive in their alerts while remaining intuitive to use.
