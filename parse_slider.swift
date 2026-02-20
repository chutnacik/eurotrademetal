import Vision
import CoreImage
import Foundation

let url = URL(fileURLWithPath: "/Users/jakubchutnak/Sites/eurotrademetal/slider.png")
guard let ciImage = CIImage(contentsOf: url) else { 
    print("Could not load image")
    exit(1) 
}
let handler = VNImageRequestHandler(ciImage: ciImage, options: [:])
let request = VNRecognizeTextRequest { request, error in
    guard let observations = request.results as? [VNRecognizedTextObservation] else { return }
    for observation in observations {
        let topCandidate = observation.topCandidates(1).first
        if let string = topCandidate?.string {
            let boundingBox = observation.boundingBox
            print("[\(boundingBox.origin.x), \(boundingBox.origin.y)] - \(string)")
        }
    }
}
try? handler.perform([request])
