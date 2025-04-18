The Rover project aims to prototype a survey configuration in REDCap to assess its feasibility as a global intake system for Stanford Medicine. The scope includes two phases:
1. Architect a new REDCap workflow for parent and child projects.
2. Develop a Universal Dashboard via an external module, represented by this repository.

### Configuration Notes: 
- Each child project should have all variables in the parent intake and mutable intake forms. 
- Child form names have to match parent form names exactly - they will otherwise fail to copy over due to the "_complete" variables being based on the survey name.
- Name mismatches will result in data not being copied from parent to child

### Requirements:
1. The Mutable survey in the parent project must have an additional variable `last_editing_user`
2. The Mutable survey in the parent project must have a field that houses JSON text for file caching (specified in EM settings)
3. Each child project must have an additional variable `universal_id` within the immutable intake survey
4. Each child project must have an additional variable `dashboard_submission_user` within their first survey (own)
5. Each child project is required to have an additional field `univ_last_update` present in the mutable intake form to house the last updated timestamp
   - This timestamp field will reflect `survey completion time` of the universal intake (mutable survey)
   - **Editing this record from the parent project outside of survey view will not update this time to children projects**
6. Each child project is required to have an additional field set in EM Settings for statuses that get reflected back to the user.
7. Each child project is required to have an additional field set in EM Settings for status change date that get reflected back to the user.

Current statuses: 
```php
$statusMap = [
                "1", // Processing" : Admins explicitly allow mutable data to overwrite this child
                "2", //"Processing - updates locked" : Updates locked, same as above but prevents any further updates to child
                "3", //"Complete"
                "4", //"Unable to process"
                "5", //"Processing - awaiting additional updates" - functionally the same as 1
                "77", // Default
                "99" // Canceled
                
            ];
```


### Default naming:
- Immutable survey (1) : `Intake`
- Mutable survey (2) : `Mutable Intake`


