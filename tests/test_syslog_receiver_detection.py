import json
import os
import shutil
import tempfile
import unittest
from pathlib import Path

import syslog_receiver


class FakeNetwork:
    def __init__(self):
        self.private_ip = "192.168.1.10"

    def persist_server_status(self):
        pass


class FakeContainment:
    def __init__(self, sort_engine):
        self.sort_engine = sort_engine

    def forward_log_to_hq(self, log_object):
        pass


class SyslogReceiverDetectionTest(unittest.TestCase):
    def setUp(self):
        self.tmpdir = tempfile.mkdtemp(prefix="siem-syslog-test-", dir="/tmp")
        self.old_cwd = os.getcwd()
        os.chdir(self.tmpdir)
        os.makedirs("captured_logs", exist_ok=True)

    def tearDown(self):
        os.chdir(self.old_cwd)
        shutil.rmtree(self.tmpdir, ignore_errors=True)

    def test_syslog_message_triggers_detection(self):
        server = syslog_receiver.SyslogServer(output_dir="captured_logs")

        from pythonSIEMscript import Sort_event, Parse_logs

        sort_engine = Sort_event()
        network_engine = FakeNetwork()
        containment_engine = FakeContainment(sort_engine)
        parser = Parse_logs(sort_engine, network_engine, containment_engine)
        server.parser = parser

        message = (
            "<34>Apr  1 00:00:00 host sudo: user admin : TTY=pts/0 ; "
            "PWD=/tmp ; USER=root ; COMMAND=/bin/bash"
        )

        server.process_syslog(message, ("192.0.2.10", 514))

        events_path = Path("captured_logs/security_events.json")
        self.assertTrue(events_path.exists(), "Security event file was not created")

        with events_path.open("r") as handle:
            events = json.load(handle)

        self.assertTrue(events, "No security events were recorded")
        self.assertEqual(events[-1]["attack_type"], "Privilege Escalation Risk")


if __name__ == "__main__":
    unittest.main()
