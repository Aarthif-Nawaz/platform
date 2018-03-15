@oauth2Skip
Feature: Testing the Form Contacts API

    Scenario: Creating a new Contact
        Given that I want to make a new "Contact"
        And that the request "data" is:
            """
            {
                "contacts":"091532899, 22223241",
                "country_code": 1
            }
            """
        When I request "/forms/1/contacts"
        Then the response is JSON
        Then the guzzle status code should be 200