;{
    const module = ExternalModules.Stanford.IntakeDashboard;

    if (!window.ExternalModules.moduleQueuedAjax) {
        console.error("moduleQueuedAjax is not defined!");
    } else {
        console.log("moduleQueuedAjax is defined.");
    }

    Object.assign(module, {
        TestFunction: function () {
            console.log("Test");
        },
    });
}
