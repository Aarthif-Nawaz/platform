workflow "New workflow" {
  on = "issues"
  resolves = ["GitHub Action for Slack"]
}

action "GitHub Action for Slack" {
  uses = "Ilshidur/action-slack@6aeb2acb39f91da283faf4c76898a723a03b2264"
  secrets = ["SLACK_WEBHOOK"]
  args = "New issue in platform {{ GITHUB_ACTION }} - as {{ EVENT_PAYLOAD.action }} with title {{ EVENT_PAYLOAD.issue.title }} and url {{ EVENT_PAYLOAD.issue.url }} "
}
