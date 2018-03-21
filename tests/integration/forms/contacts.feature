@oauth2Skip
Feature: Testing the Form Contacts API

    Scenario: Creating a valid set of Contacts for a targeted survey
        Given that I want to make a new "Contact"
        And that the request "data" is:
            """
            {
                "contacts":"59899333222, 59891333222",
                "country_code": "UY"
            }
            """
        When I request "/forms/5/contacts"
        Then the response is JSON
        And the response has a "form_id" property
        And the type of the "form_id" property is "numeric"
        And the response has a "count" property
        And the type of the "count" property is "numeric"
        Then the guzzle status code should be 200

    Scenario: Creating an invalid set of Contacts for a targeted survey
        Given that I want to make a new "Contact"
        And that the request "data" is:
            """
            {
                "contacts":"912899, 223241",
                "country_code": "UY"
            }
            """
        When I request "/forms/5/contacts"
        Then the response is JSON
        Then the guzzle status code should be 422
        And the response has a "errors" property
        And the response has a "errors.1" property
        And the response has a "errors.1.title.contact" property
        And the type of the "errors.1.title.contact" property is "string"
        Then the "errors.1.title.contact" property equals "Invalid phone number"

    Scenario: Creating a new valid set of Contacts for a non targeted survey
        Given that I want to make a new "Contact"
        And that the request "data" is:
            """
            {
                "contacts":"912899, 223241",
                "country_code": "UY"
            }
            """
        When I request "/forms/1/contacts"
        Then the response is JSON
        Then the guzzle status code should be 400
        And the response has a "errors" property
        And the response has a "errors.0.title" property
        And the type of the "errors.0.title" property is "string"
        Then the "errors.0.title" property equals "Not a targeted survey"


