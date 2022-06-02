<?php

require 'fedora_user_switch.php';

$files_count = \Drupal::entityQuery('file')->condition('uri','public://%', 'LIKE')->count()->accessCheck(FALSE)->execute();
print("Checking $files_count files...\n");
$batch_size = 1000;
$utils = \Drupal::service('islandora.utils');
for ($index = 0; $index <= $files_count; $index += $batch_size) {
    $query = \Drupal::entityQuery('file')->condition('uri','public://%', 'LIKE')->range($index,$batch_size)->accessCheck(FALSE);
    $fids = $query->execute();
    print("Range $index\n");
    foreach (\Drupal::entityTypeManager()->getStorage('file')->loadMultiple($fids) as $file) {
        if (!file_exists($file->getFileUri())) {
            print("Deleting missing: ".$file->getFileUri()."\n");
            $referenced_media = $utils->getReferencingMedia($file->id());
            foreach ($referenced_media as $media) {
                print("Deleting Media ".$media->label()."\n");
                $media->delete();
            }
            $file->delete();
	}
    }
}
