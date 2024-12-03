;{
    const module = ExternalModules.Stanford.IntakeDashboard;

    if (!window.ExternalModules.moduleQueuedAjax) {
        console.error("moduleQueuedAjax is not defined!");
    } else {
        console.log("moduleQueuedAjax is defined.");
    }

    Object.assign(module, {
        fetchIntakeParticipation: function (callback, errCallback) {
            module.ajax('fetchIntakeParticipation').then(function (res) {
                let parsed = JSON.parse(res)
                if(parsed.success){
                    callback(parsed);
                } else {
                    errCallback(parsed)
                }
            }).catch(function (err) {
                console.error("Catch triggered in fetchIntakeParticipation, error: ", err);
            })
        },
        checkUserDetailAccess: function(payload, callback, errCallback) {
          module.ajax('checkUserDetailAccess', payload).then(function (res) {
              let parsed = JSON.parse(res)
              if(parsed.success){
                  callback(parsed);
              } else {
                  errCallback(parsed)
              }
          })
        },
        fetchRequiredSurveys: function(payload, callback, errCallback) {
            module.ajax('fetchRequiredSurveys', payload).then(function (res) {
                let parsed = JSON.parse(res)
                if(parsed.success){
                    callback(parsed);
                } else {
                    errCallback(parsed)
                }
            })
        }
    });
}
