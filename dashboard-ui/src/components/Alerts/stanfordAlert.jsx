import { Transition } from '@mantine/core';

function StanfordAlert({mounted, children}) {
    return (
        <Transition mounted={mounted} enterDelay={300} transition="fade" duration={300} timingFunction="ease">
            {(styles) => <div style={styles}>{children}</div>}
        </Transition>
    );
}

export default StanfordAlert;