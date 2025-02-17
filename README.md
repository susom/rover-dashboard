The Rover project aims to prototype a survey configuration in REDCap to assess its feasibility as a global intake system for Stanford Medicine. The scope includes two phases:
1. Architect a new REDCap workflow for parent and child projects.
2. Develop a Universal Dashboard via an external module, represented by this repository.

### Configuration Notes: 
- Each child project should have all variables in the parent intake and mutable intake forms.
- Each child project is required to have an additional field `univ_last_update` present in the mutable intake form to house the last updated timestamp
  - This timestamp field will reflect `survey completion time` of the universal intake (mutable survey)
  - **Editing this record from the parent project outside of survey view will not update this time to children projects**