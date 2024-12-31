;{
    const module = ExternalModules.Stanford.IntakeDashboard;

    if (!window.ExternalModules.moduleQueuedAjax) {
        console.error("moduleQueuedAjax is not defined!");
    } else {
        console.log("moduleQueuedAjax is defined.");
    }

    // Helper function to handle repetitive logic
    function handleAjaxResponse(promise, callback, errCallback) {
        promise
            .then((res) => {
                let parsed;
                try {
                    parsed = JSON.parse(res);
                } catch (err) {
                    console.error("Failed to parse JSON: ", err);
                    errCallback({ success: false, error: "Invalid JSON" });
                    return;
                }
                if (parsed.success) {
                    callback(parsed);
                } else {
                    errCallback(parsed);
                }
            })
            .catch((err) => {
                console.error("Ajax error: ", err);
                errCallback({ success: false, error: err.message });
            });
    }

    Object.assign(module, {
        fetchIntakeParticipation(callback, errCallback) {
            handleAjaxResponse(module.ajax('fetchIntakeParticipation'), callback, errCallback);
        },
        getUserDetail(payload, callback, errCallback) {
            handleAjaxResponse(module.ajax('getUserDetail', payload), callback, errCallback);
        },
        fetchRequiredSurveys(payload, callback, errCallback) {
            handleAjaxResponse(module.ajax('fetchRequiredSurveys', payload), callback, errCallback);
        },
        toggleProjectActivation(payload, callback, errCallback) {
            handleAjaxResponse(module.ajax('toggleProjectActivation', payload), callback, errCallback);
        },
    });

    // Object.assign(module, {
    //     fetchIntakeParticipation: function (callback, errCallback) {
    //         module.ajax('fetchIntakeParticipation').then(function (res) {
    //             let parsed = JSON.parse(res)
    //             if(parsed.success){
    //                 callback(parsed);
    //             } else {
    //                 errCallback(parsed)
    //             }
    //         }).catch(function (err) {
    //             console.error("Catch triggered in fetchIntakeParticipation, error: ", err);
    //         })
    //     },
    //     getUserDetail: function(payload, callback, errCallback) {
    //       module.ajax('getUserDetail', payload).then(function (res) {
    //           let parsed = JSON.parse(res)
    //           if(parsed.success){
    //               callback(parsed);
    //           } else {
    //               errCallback(parsed)
    //           }
    //       })
    //     },
    //     fetchRequiredSurveys: function(payload, callback, errCallback) {
    //         module.ajax('fetchRequiredSurveys', payload).then(function (res) {
    //             let parsed = JSON.parse(res)
    //             if(parsed.success){
    //                 callback(parsed);
    //             } else {
    //                 errCallback(parsed)
    //             }
    //         })
    //     },
    //     toggleProjectActivation: function(payload, callback, errCallback) {
    //         module.ajax('toggleProjectActivation', payload).then(function (res) {
    //             let parsed = JSON.parse(res)
    //             if(parsed.success){
    //                 callback(parsed);
    //             } else {
    //                 errCallback(parsed)
    //             }
    //         })
    //     }
    // });
}
