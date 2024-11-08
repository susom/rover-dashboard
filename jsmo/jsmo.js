;{
    const module = ExternalModules.Stanford.IntakeDashboard;

    if (!window.ExternalModules.moduleQueuedAjax) {
        console.error("moduleQueuedAjax is not defined!");
    } else {
        console.log("moduleQueuedAjax is defined.");
    }

    Object.assign(module, {
        fetchIntakeParticipation: function (callback) {
            module.ajax('fetchIntakeParticipation').then(function (res) {
                if(res){
                    callback(JSON.parse(res));
                }

                // if(res) {
                //     setStateVarCallBack(res)
                // }
            }).catch(function (err) {
                console.error("Catch triggered in fetchIntakeParticipation, error: ", err);
            })
        }
    });
}
