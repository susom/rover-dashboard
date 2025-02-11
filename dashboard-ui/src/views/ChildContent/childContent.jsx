import React, {useState, useEffect, useCallback} from "react";
// import { useDisclosure } from '@mantine/hooks';
// import {IconExternalLink, IconInfoCircle} from '@tabler/icons-react';
// import {useNavigate} from "react-router-dom";
// import { AppHeader } from '../../components/AppHeader/appHeader'; // Import the reusable header
// import { IconPlus } from '@tabler/icons-react';

import {RequestTable} from '../../components/RequestTable/requestTable.jsx';
import {LoadingOverlay, Button, Tooltip, Alert, List, Modal, Badge, Table} from "@mantine/core";
import {IconBook, IconExternalLink, IconInfoCircle, IconPlus} from "@tabler/icons-react";
import {useDisclosure} from "@mantine/hooks";



export function ChildContent({childInfo, immutableParentInfo, mutableParentInfo}) {
    let jsmoModule;
    const [opened, { open, close }] = useDisclosure(false);
    const [childOpened, { open: childOpen, close: childClose }] = useDisclosure(false);
    const [submissions, setSubmissions] = useState([])
    const [loading, setLoading] = useState(true)
    const [childSelected, setChildSelected] = useState(null)

    if (import.meta?.env?.MODE !== 'development')
        jsmoModule = ExternalModules.Stanford.IntakeDashboard;

    useEffect(() => {
        setLoading(true)
        setSubmissions([])

        jsmoModule.getChildSubmissions(
            {'child_pid': childInfo?.child_id, 'universal_id': immutableParentInfo?.record_id},
            (res) => {
                setLoading(false)
                setSubmissions(res?.data || [])
            },
            (err) => {
                console.log(err)
                setLoading(false)
            }
        )
    }, [childInfo, immutableParentInfo])

    const successCallback = (res) => {
        if(res?.url){
            window.open(res.url, "_self") //redirect to survey
        }
    }

    const errorCallback = (err) => {
        console.log(err)
    }

    const onClick = (e) => {
        jsmoModule.newChildRequest({'child_id': childInfo?.child_id, 'universal_id': immutableParentInfo?.record_id}, successCallback, errorCallback)
    }

    const renderInteraction = (data) => {
        if(data?.survey_url){
            if(immutableParentInfo?.intake_active === "0") {
                return <Badge color="red" radius="sm">Canceled</Badge>;
            }

            return (
                <Button
                    size="xs"
                    color="green"
                    component="a"
                    href={data.survey_url}
                    rightSection={<IconExternalLink size={20} />}
                >
                    Edit
                </Button>
            )
        } else {
            let findChild = submissions.findIndex(e => e.record_id === data.record_id);
            return (
                <Button
                    size="xs"
                    color="blue"
                    variant="light"
                    childIndex = {findChild}
                    rightSection={<IconBook size={16} />}
                    onClick={() => {
                        setChildSelected(findChild)
                        childOpen()
                    }}
                >
                    View
                </Button>
            )
        }
    }

    const filterStatuses = (num) => {
        if(num === "0")
            return "Incomplete"
        else if(num === "2")
            return "Complete"
    }

    const renderChildModal = () => {
        const tab = {
            caption: 'Survey Details',
            head: ['Variable', "Label", "Value"],
            body: submissions[childSelected]?.completed_form_pretty
                ? Object.entries(submissions[childSelected]?.completed_form_pretty).map(([key, v]) => [key, v.label, v.value || ""])
                : [],
        }

        return (
            <Table.ScrollContainer h={520}>
                <Table
                    stickyHeader
                    striped
                    data={tab}
                />
            </Table.ScrollContainer>
        );
    }

    let body = submissions.map(e => [
        e.record_id,
        filterStatuses(e?.child_survey_complete),
        e?.survey_completion_ts ? e.survey_completion_ts : "N/A",
        renderInteraction(e)]
    )

    const renderRequestButton = () => {
        let label

        if(immutableParentInfo?.intake_active === "0") { //If deactivated
            return (
                <Tooltip label="This intake is inactive, no requests can be submitted">
                    <Button
                        onClick={e => e.preventDefault()}
                        rightSection={<IconPlus size={20} />}
                        component="a"
                        m="sm"
                        data-disabled
                        disabled
                    >New Request</Button>
                </Tooltip>
            )
        } else {
            if(mutableParentInfo?.complete !== "2") {
                return (
                    <Tooltip label="Please complete universal survey II">
                        <Button
                            onClick={e => e.preventDefault()}
                            rightSection={<IconPlus size={20} />}
                            component="a"
                            m="sm"
                            data-disabled
                            disabled
                        >New Request</Button>
                    </Tooltip>
                )
            } else {
                return (
                    <Button
                        onClick={open}
                        rightSection={<IconPlus size={20} />}
                        component="a"
                        m="sm"
                    >New Request</Button>
                )
            }
        }
    }

    return (
        <div>
            <Modal title={`New Request - ${childInfo?.title}`} size="xl" opened={opened} onClose={close} centered>
                <Alert variant="light" color="blue" title="Notice" icon={<IconInfoCircle/>}>
                    Are you sure you want to create a new request?
                    <List size="sm">
                        <List.Item>Continuing with this process will create a new ticket in the corresponding team's queue</List.Item>
                        <List.Item>New Requests are required if amending a previous request or asking for additional work to be done</List.Item>
                        <List.Item>Please complete any previously submitted surveys before creating additional requests</List.Item>
                    </List>
                </Alert>
                <div style={{display: 'flex', justifyContent: 'center', marginTop: '1rem'}}>
                    <Button
                        onClick={onClick}
                    >Confirm</Button>
                </div>
            </Modal>
            <LoadingOverlay visible={loading} loaderProps={{ children: 'Loading...' }} />
            {renderRequestButton()}
            <Modal size="80%" opened={childOpened} onClose={childClose} title="Child Intake Submission">
                {childOpened && renderChildModal()}
            </Modal>
            <RequestTable
                columns={['Child ID', 'Request Submission', 'Submission Timestamp', 'Survey Link']}
                body={body}
            />
        </div>

    )
}