import React, {useState, useEffect, useCallback} from "react";
// import {AppShell, Button, Group, Card, Image, Table, Text, Divider, Pagination, Title, Blockquote, List, Loader} from '@mantine/core';
// import { useDisclosure } from '@mantine/hooks';
// import {IconExternalLink, IconInfoCircle} from '@tabler/icons-react';
// import {useNavigate} from "react-router-dom";
// import { AppHeader } from '../../components/AppHeader/appHeader'; // Import the reusable header
// import { IconPlus } from '@tabler/icons-react';

import {RequestTable} from '../../components/RequestTable/requestTable.jsx';
import {Box, Button} from "@mantine/core";
import {IconExternalLink, IconPlus} from "@tabler/icons-react";



export function ChildContent({childInfo, parentInfo}) {
    let jsmoModule;
    const [submissions, setSubmissions] = useState([])

    if (import.meta?.env?.MODE !== 'development')
        jsmoModule = ExternalModules.Stanford.IntakeDashboard;

    useEffect(() => {
        console.log('attempting to fetch child submissions...')
        jsmoModule.getChildSubmissions(
            {'child_id': childInfo?.child_id, 'universal_id': parentInfo?.record_id},
            (res) => setSubmissions(res?.data || []),
            (err) => console.log(err)
        )
    }, [])

    const successCallback = (res) => {
        if(res?.url){
            window.open(res.url, "_self") //redirect to survey
        }
    }

    const errorCallback = (err) => {
        console.log(err)
    }

    const onClick = (e) => {
        console.log(e.currentTarget.id)
        jsmoModule.newChildRequest({'child_id': e.currentTarget.id, 'universal_id': parentInfo?.record_id}, successCallback, errorCallback)
    }

    console.log(parentInfo)
    const renderEditButton = () => {
        return (
            <Button
                size="xs"
                color="green"
                component="a"
                href={childInfo?.url}
                rightSection={<IconExternalLink size={20} />}
            >
                Edit
            </Button>
        )
    }

    const filterStatuses = (num) => {
        if(num === "0")
            return "Incomplete"
        else if(num === "2")
            return "Complete"
    }

    let body = submissions.map(e => [
        e.record_id,
        filterStatuses(e.ids_survey_demo_complete),
        renderEditButton()]
    )

    return (
        <div>
            <Button
                onClick = {onClick}
                rightSection={<IconPlus size={20} />}
                component="a"
                m="sm"
                id={childInfo?.child_id}
            >New Request</Button>
            <RequestTable
                caption="Test Table 1"
                columns={['Child ID', 'Status', 'Survey']}
                body={body}
            />
        </div>

    )
}