import React, {useState, useEffect, useCallback} from "react";
// import { useDisclosure } from '@mantine/hooks';
// import {IconExternalLink, IconInfoCircle} from '@tabler/icons-react';
// import {useNavigate} from "react-router-dom";
// import { AppHeader } from '../../components/AppHeader/appHeader'; // Import the reusable header
// import { IconPlus } from '@tabler/icons-react';

import {RequestTable} from '../../components/RequestTable/requestTable.jsx';
import {LoadingOverlay, Button, Tooltip, Alert, List, Modal, Text} from "@mantine/core";
import {IconExternalLink, IconInfoCircle, IconPlus} from "@tabler/icons-react";
import {useDisclosure} from "@mantine/hooks";



export function ChildContent({childInfo, immutableParentInfo, mutableParentInfo}) {
    let jsmoModule;
    const [opened, { open, close }] = useDisclosure(false);
    const [submissions, setSubmissions] = useState([])
    const [loading, setLoading] = useState(true)

    if (import.meta?.env?.MODE !== 'development')
        jsmoModule = ExternalModules.Stanford.IntakeDashboard;

    useEffect(() => {
        setLoading(true)
        setSubmissions([])

        jsmoModule.getChildSubmissions(
            {'child_id': childInfo?.child_id, 'universal_id': immutableParentInfo?.record_id},
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
        jsmoModule.newChildRequest({'child_id': e.currentTarget.id, 'universal_id': immutableParentInfo?.record_id}, successCallback, errorCallback)
    }

    const renderEditButton = (url) => {
        if(url){
            return (
                <Button
                    size="xs"
                    color="green"
                    component="a"
                    href={url}
                    rightSection={<IconExternalLink size={20} />}
                >
                    Edit
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

    let body = submissions.map(e => [
        e.record_id,
        filterStatuses(e?.child_survey_complete),
        e?.survey_completion_ts ? e.survey_completion_ts : "N/A",
        renderEditButton(e.survey_url)]
    )

    const renderButton = () => {
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
                        id={childInfo?.child_id}
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
                    id={childInfo?.child_id}
                >New Request</Button>
            )
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
                    >Confirm</Button>
                </div>
            </Modal>
            <LoadingOverlay visible={loading} loaderProps={{ children: 'Loading...' }} />
            {renderButton()}
            <RequestTable
                columns={['Child ID', 'Request Submission', 'Submission Timestamp', 'Survey Link']}
                body={body}
            />
        </div>

    )
}