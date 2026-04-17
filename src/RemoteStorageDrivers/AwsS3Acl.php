<?php declare(strict_types = 1);

namespace Mangoweb\MonologTracyHandler\RemoteStorageDrivers;


enum AwsS3Acl: string
{
	case Private = 'private';
	case PublicRead = 'public-read';
	case PublicReadWrite = 'public-read-write';
	case AuthenticatedRead = 'authenticated-read';
	case AwsExecRead = 'aws-exec-read';
	case BucketOwnerRead = 'bucket-owner-read';
	case BucketOwnerFullControl = 'bucket-owner-full-control';
	case LogDeliveryWrite = 'log-delivery-write';
}
