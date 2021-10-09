<?php

abstract class UploadType
{
	const Queued = 0;
	const Invalid = 1;
	const Sent = 2;
	const Attachment = 3;
}